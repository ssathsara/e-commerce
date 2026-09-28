<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Services\Wishlist\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlists) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->wishlists->for($request->user())
            ->items()
            ->whereHas('product')
            ->with(['product' => fn ($q) => $q->with(['primaryImage', 'brand:id,name,slug', 'category:id,name,slug'])->withCount('activeVariants')])
            ->get();

        return $this->success($items->map(fn ($item) => [
            'id' => $item->id,
            'added_at' => $item->created_at?->toIso8601String(),
            'product' => (new ProductCardResource($item->product))->resolve($request),
        ])->values());
    }

    /** Ids only: lets the storefront mark wishlisted products cheaply. */
    public function ids(Request $request): JsonResponse
    {
        return $this->success($this->wishlists->productIds($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'min:1']]);

        $this->wishlists->add($request->user(), (int) $data['product_id']);

        return $this->created(null, 'Added to your wishlist.');
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $this->wishlists->remove($request->user(), $productId);

        return $this->message('Removed from your wishlist.');
    }

    public function moveToCart(Request $request, int $productId): JsonResponse
    {
        $data = $request->validate(['variant_id' => ['nullable', 'integer', 'min:1']]);

        $this->wishlists->moveToCart($request->user(), $productId, $data['variant_id'] ?? null);

        return $this->message('Moved to your cart.');
    }
}
