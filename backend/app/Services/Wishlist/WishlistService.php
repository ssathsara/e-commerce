<?php

namespace App\Services\Wishlist;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\Cart\CartService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function __construct(private readonly CartService $carts) {}

    public function for(User $user): Wishlist
    {
        return Wishlist::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /** Idempotent: adding a product twice keeps a single entry. */
    public function add(User $user, int $productId): WishlistItem
    {
        $product = Product::query()->visible()->find($productId)
            ?? throw BusinessRuleException::unprocessable('This product is not available.', 'product_id');

        $wishlist = $this->for($user);

        try {
            return $wishlist->items()->firstOrCreate(['product_id' => $product->id]);
        } catch (UniqueConstraintViolationException) {
            return $wishlist->items()->where('product_id', $product->id)->firstOrFail();
        }
    }

    public function remove(User $user, int $productId): void
    {
        $this->for($user)->items()->where('product_id', $productId)->delete();
    }

    /**
     * Adds the product to the cart (quantity 1) and removes it from the wishlist.
     * Products with variants need a variant choice, so the variant id is required for them.
     */
    public function moveToCart(User $user, int $productId, ?int $variantId): void
    {
        DB::transaction(function () use ($user, $productId, $variantId) {
            $cart = $this->carts->resolve($user, null, create: true);
            $this->carts->add($cart, $productId, $variantId, 1);
            $this->remove($user, $productId);
        });
    }

    /** @return list<int> */
    public function productIds(User $user): array
    {
        return WishlistItem::query()
            ->whereHas('wishlist', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('product_id')
            ->all();
    }
}
