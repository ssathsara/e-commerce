<?php

namespace App\Services\Checkout;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Exceptions\BusinessRuleException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartLine;
use App\Services\Cart\CartService;
use App\Services\Coupons\CouponService;
use App\Services\Inventory\InventoryService;
use App\Services\Payments\PaymentService;
use App\Support\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns the customer's cart into an order.
 *
 * Guarantees:
 *  - Idempotent per (user, idempotency key): retries/double-clicks return the original order.
 *  - Atomic: order, items, stock deduction, inventory movements, coupon usage and cart
 *    clearing either all happen or none do.
 *  - Race-safe: product/variant/coupon rows are locked FOR UPDATE (in id order) so two
 *    customers can never buy the same last unit, and stock can never go negative.
 *  - Price-safe: prices come from the locked catalogue rows, never from the client.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CouponService $coupons,
        private readonly InventoryService $inventory,
        private readonly PaymentService $payments,
    ) {}

    public function placeOrder(User $user, PlaceOrderData $data): CheckoutResult
    {
        $existing = $this->findByIdempotencyKey($user, $data->idempotencyKey);
        if ($existing !== null) {
            return new CheckoutResult($existing, created: false);
        }

        try {
            $order = DB::transaction(fn () => $this->createOrder($user, $data));
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same key won the race.
            $existing = $this->findByIdempotencyKey($user, $data->idempotencyKey);
            if ($existing === null) {
                throw $e;
            }

            return new CheckoutResult($existing, created: false);
        }

        $paymentError = null;

        if ($data->paymentMethod->isOnline()) {
            $payment = $this->payments->charge($order, (string) $data->paymentToken, "order-{$order->id}-initial");

            if ($payment->status === PaymentStatus::Failed) {
                $paymentError = $payment->failure_reason;
            }
        }

        return new CheckoutResult($order->refresh(), created: true, paymentError: $paymentError);
    }

    private function createOrder(User $user, PlaceOrderData $data): Order
    {
        // Only locking reads happen before the catalogue rows are locked. Under InnoDB's
        // REPEATABLE READ the snapshot is taken at the first non-locking read, so this
        // guarantees the summary below sees the latest committed prices and stock.
        $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();
        $items = $cart?->items()->lockForUpdate()->get(['id', 'product_id', 'product_variant_id']);

        if ($cart === null || $items->isEmpty()) {
            throw BusinessRuleException::unprocessable('Your cart is empty.', 'cart');
        }

        $this->lockCatalogueRows($items);

        $summary = $this->carts->summarize($cart, $user, $data->shippingMethod);

        if ($summary->hasIssues()) {
            $errors = collect($summary->lines)
                ->filter(fn (CartLine $line) => $line->issue !== null)
                ->map(fn (CartLine $line) => ($line->item->product?->name ?? 'Item').': '.$line->issue)
                ->values()
                ->all();

            throw BusinessRuleException::conflict('Some items in your cart need attention before checkout.', ['cart' => $errors]);
        }

        $coupon = null;
        if ($summary->coupon !== null) {
            if ($summary->couponError !== null) {
                throw BusinessRuleException::unprocessable($summary->couponError, 'coupon_code');
            }

            // Re-validate under lock so usage limits cannot be exceeded concurrently.
            $coupon = $this->coupons->validate($summary->coupon, $summary->totals->subtotal, $user, lock: true);
        }

        $totals = $summary->totals;
        $shipping = Address::query()->where('user_id', $user->id)->findOrFail($data->shippingAddressId);
        $billing = Address::query()->where('user_id', $user->id)->findOrFail($data->billingAddressId);

        $order = new Order([
            'notes' => $data->notes,
        ]);
        $order->forceFill([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => $data->paymentMethod,
            'shipping_method' => $data->shippingMethod,
            'currency' => config('shop.currency'),
            'subtotal' => $totals->subtotal,
            'discount_total' => $totals->discount,
            'shipping_total' => $totals->shipping,
            'tax_total' => $totals->tax,
            'grand_total' => $totals->total,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'shipping_address' => $shipping->toSnapshot(),
            'billing_address' => $billing->toSnapshot(),
            'customer_email' => $user->email,
            'idempotency_key' => $data->idempotencyKey,
        ])->save();

        foreach ($summary->lines as $line) {
            $item = $line->item;
            $product = $item->product;
            $variant = $item->variant;

            $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'sku' => $variant?->sku ?? $product->sku,
                'unit_price' => $line->unitPrice,
                'quantity' => $item->quantity,
                'line_total' => $line->lineTotal,
            ]);

            $this->inventory->adjust($product, $variant, -$item->quantity, InventoryMovementType::Sale, $user, null, $order);

            Product::query()->whereKey($product->id)->increment('sales_count', $item->quantity);
        }

        if ($coupon !== null) {
            $this->coupons->recordUsage($coupon, $order, $totals->discount);
        }

        if (! $data->paymentMethod->isOnline()) {
            $this->payments->recordOfflinePayment($order);
        }

        $this->carts->clear($cart);

        AuditLog::info('order.placed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $user->id,
            'total' => $order->grand_total,
        ]);

        event(new OrderPlaced($order));

        return $order;
    }

    /**
     * Lock every product (then variant) in the cart in ascending id order.
     * The consistent ordering prevents deadlocks between concurrent checkouts.
     */
    private function lockCatalogueRows(Collection $items): void
    {
        Product::withTrashed()
            ->whereIn('id', $items->pluck('product_id')->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        $variantIds = $items->pluck('product_variant_id')->filter()->unique();
        if ($variantIds->isNotEmpty()) {
            ProductVariant::query()->whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    private function findByIdempotencyKey(User $user, string $key): ?Order
    {
        return Order::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
