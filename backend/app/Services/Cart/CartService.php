<?php

namespace App\Services\Cart;

use App\Enums\ProductStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Checkout\ShippingService;
use App\Services\Checkout\TotalsCalculator;
use App\Services\Coupons\CouponService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carts belong to an authenticated user or to a guest identified by an opaque
 * UUID token (sent by the SPA in the X-Cart-Token header). Guest carts are merged
 * into the user's cart on login/registration.
 *
 * Carts hold only product/variant ids and quantities. Prices, availability and
 * totals are always recomputed from the catalogue.
 */
class CartService
{
    public function __construct(
        private readonly TotalsCalculator $calculator,
        private readonly CouponService $coupons,
    ) {}

    public function resolve(?User $user, ?string $guestToken, bool $create = false): ?Cart
    {
        if ($user !== null) {
            return $create
                ? Cart::query()->firstOrCreate(['user_id' => $user->id])
                : Cart::query()->where('user_id', $user->id)->first();
        }

        $cart = $this->isValidToken($guestToken)
            ? Cart::query()->whereNull('user_id')->where('token', $guestToken)->first()
            : null;

        if ($cart === null && $create) {
            $cart = Cart::create(['token' => (string) Str::uuid()]);
        }

        return $cart;
    }

    public function add(Cart $cart, int $productId, ?int $variantId, int $quantity): CartItem
    {
        [$product, $variant] = $this->resolvePurchasable($productId, $variantId);

        return DB::transaction(function () use ($cart, $product, $variant, $quantity) {
            // Serialise concurrent writes to the same cart.
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('variant_key', $variant?->id ?? 0)
                ->first();

            $newQuantity = ($item?->quantity ?? 0) + $quantity;
            $this->assertQuantityAllowed($product, $variant, $newQuantity);

            if ($item === null) {
                $item = $cart->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $newQuantity,
                ]);
            } else {
                $item->update(['quantity' => $newQuantity]);
            }

            $cart->touch();

            return $item;
        });
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        $this->assertItemBelongsToCart($cart, $item);

        [$product, $variant] = $this->resolvePurchasable($item->product_id, $item->product_variant_id);
        $this->assertQuantityAllowed($product, $variant, $quantity);

        $item->update(['quantity' => $quantity]);
        $cart->touch();

        return $item;
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        $this->assertItemBelongsToCart($cart, $item);

        $item->delete();
        $cart->touch();
    }

    public function clear(Cart $cart): void
    {
        DB::transaction(function () use ($cart) {
            $cart->items()->delete();
            $cart->coupon()->dissociate();
            $cart->save();
        });
    }

    public function applyCoupon(Cart $cart, string $code, ?User $user): void
    {
        $coupon = $this->coupons->findByCode($code)
            ?? throw BusinessRuleException::unprocessable('This coupon code is invalid.', 'coupon_code');

        $summary = $this->summarize($cart);

        if ($summary->isEmpty()) {
            throw BusinessRuleException::unprocessable('Add items to your cart before applying a coupon.', 'coupon_code');
        }

        $this->coupons->validate($coupon, $summary->totals->subtotal, $user);

        $cart->coupon()->associate($coupon);
        $cart->save();
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->coupon()->dissociate();
        $cart->save();
    }

    /**
     * Moves a guest cart into the user's cart. Quantities are combined and capped
     * at available stock rather than failing the login.
     */
    public function mergeGuestCart(?string $guestToken, User $user): void
    {
        if (! $this->isValidToken($guestToken)) {
            return;
        }

        $guestCart = Cart::query()->whereNull('user_id')->where('token', $guestToken)->with('items')->first();

        if ($guestCart === null) {
            return;
        }

        DB::transaction(function () use ($guestCart, $user) {
            $userCart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

            foreach ($guestCart->items as $guestItem) {
                try {
                    [$product, $variant] = $this->resolvePurchasable($guestItem->product_id, $guestItem->product_variant_id);
                } catch (BusinessRuleException) {
                    continue; // Item no longer purchasable: drop it.
                }

                $existing = CartItem::query()
                    ->where('cart_id', $userCart->id)
                    ->where('product_id', $product->id)
                    ->where('variant_key', $variant?->id ?? 0)
                    ->first();

                $available = $variant?->stock_quantity ?? $product->stock_quantity;
                $quantity = min(
                    ($existing?->quantity ?? 0) + $guestItem->quantity,
                    $available,
                    (int) config('shop.cart.max_line_quantity'),
                );

                if ($quantity <= 0) {
                    continue;
                }

                $existing
                    ? $existing->update(['quantity' => $quantity])
                    : $userCart->items()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'quantity' => $quantity,
                    ]);
            }

            if ($userCart->coupon_id === null && $guestCart->coupon_id !== null) {
                $userCart->coupon_id = $guestCart->coupon_id;
                $userCart->save();
            }

            $guestCart->delete();
        });
    }

    /**
     * Price, validate and total the cart from authoritative catalogue data.
     */
    public function summarize(?Cart $cart, ?User $user = null, string $shippingMethod = ShippingService::DEFAULT_METHOD): CartSummary
    {
        $lines = [];
        $subtotal = 0;

        if ($cart !== null) {
            $cart->load([
                'items.product' => fn ($q) => $q->with(['primaryImage', 'category:id,is_active'])->withCount(['activeVariants']),
                'items.variant',
                'coupon',
            ]);

            foreach ($cart->items as $item) {
                $line = $this->priceLine($item);
                $lines[] = $line;

                if ($line->issue === null) {
                    $subtotal += $line->lineTotal;
                }
            }
        }

        $coupon = $cart?->coupon;
        $couponError = null;

        if ($coupon !== null) {
            try {
                $this->coupons->validate($coupon, $subtotal, $user);
            } catch (BusinessRuleException $e) {
                // Keep the coupon attached (conditions may be met again) but do not apply it.
                $couponError = $e->getMessage();
            }
        }

        $totals = $this->calculator->calculate($subtotal, $couponError === null ? $coupon : null, $shippingMethod);

        return new CartSummary($cart, $lines, $totals, $coupon, $couponError, $shippingMethod);
    }

    /* ----------------------------------------------------------------- */

    private function priceLine(CartItem $item): CartLine
    {
        $product = $item->product;
        $variant = $item->variant;

        if ($variant !== null && $product !== null) {
            $variant->setRelation('product', $product);
        }

        if ($product === null) {
            return new CartLine($item, 0, 0, 0, 'This product is no longer available.');
        }

        $unitPrice = $variant?->effectivePrice() ?? $product->price;
        $lineTotal = $unitPrice * $item->quantity;
        $available = $variant?->stock_quantity ?? $product->stock_quantity;

        $issue = match (true) {
            ! in_array($product->status, [ProductStatus::Active, ProductStatus::OutOfStock], true),
            ! ($product->category?->is_active ?? false) => 'This product is no longer available.',
            $variant === null && $product->active_variants_count > 0 => 'Please choose an option for this product.',
            $variant !== null && ! $variant->is_active => 'The selected option is no longer available.',
            $available <= 0 => 'This item is out of stock.',
            $item->quantity > $available => "Only {$available} left in stock.",
            default => null,
        };

        return new CartLine($item, $unitPrice, $lineTotal, $available, $issue);
    }

    /**
     * @return array{0: Product, 1: ?ProductVariant}
     */
    private function resolvePurchasable(int $productId, ?int $variantId): array
    {
        $product = Product::query()->visible()->withCount('activeVariants')->find($productId);

        if ($product === null) {
            throw BusinessRuleException::unprocessable('This product is not available.', 'product_id');
        }

        $variant = null;

        if ($product->active_variants_count > 0) {
            if ($variantId === null) {
                throw BusinessRuleException::unprocessable('Please choose an option for this product.', 'variant_id');
            }

            $variant = $product->variants()->where('is_active', true)->find($variantId)
                ?? throw BusinessRuleException::unprocessable('The selected option is not available.', 'variant_id');
            $variant->setRelation('product', $product);
        } elseif ($variantId !== null) {
            throw BusinessRuleException::unprocessable('The selected option is not available.', 'variant_id');
        }

        if ($product->status !== ProductStatus::Active) {
            throw InsufficientStockException::forItem($product->name, 0);
        }

        return [$product, $variant];
    }

    private function assertQuantityAllowed(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $max = (int) config('shop.cart.max_line_quantity');

        if ($quantity < 1) {
            throw BusinessRuleException::unprocessable('Quantity must be at least 1.', 'quantity');
        }

        if ($quantity > $max) {
            throw BusinessRuleException::unprocessable("You can order at most {$max} of a single item.", 'quantity');
        }

        $available = $variant?->stock_quantity ?? $product->stock_quantity;

        if ($quantity > $available) {
            $name = $variant ? "{$product->name} ({$variant->name})" : $product->name;

            throw InsufficientStockException::forItem($name, $available);
        }
    }

    private function assertItemBelongsToCart(Cart $cart, CartItem $item): void
    {
        if ($item->cart_id !== $cart->id) {
            // Do not reveal whether the item exists in someone else's cart.
            throw new ModelNotFoundException;
        }
    }

    private function isValidToken(?string $token): bool
    {
        return $token !== null && Str::isUuid($token);
    }
}
