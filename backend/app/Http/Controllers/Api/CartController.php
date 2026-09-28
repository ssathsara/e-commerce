<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\ApplyCouponRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use App\Services\Checkout\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cart endpoints work for guests (X-Cart-Token header) and signed-in users.
 * Every response returns the full server-computed cart.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly ShippingService $shipping,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request, $this->cart($request));
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->cart($request, create: true);

        $this->carts->add(
            $cart,
            (int) $request->validated('product_id'),
            $request->validated('variant_id') !== null ? (int) $request->validated('variant_id') : null,
            (int) $request->validated('quantity'),
        );

        return $this->respond($request, $cart, 'Added to cart.', 201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        $cart = $this->cartOrFail($request);
        $this->carts->updateQuantity($cart, $item, (int) $request->validated('quantity'));

        return $this->respond($request, $cart, 'Cart updated.');
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->cartOrFail($request);
        $this->carts->remove($cart, $item);

        return $this->respond($request, $cart, 'Item removed.');
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartOrFail($request);
        $this->carts->clear($cart);

        return $this->respond($request, $cart, 'Cart cleared.');
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $cart = $this->cartOrFail($request);
        $this->carts->applyCoupon($cart, $request->validated('coupon_code'), $request->user('sanctum'));

        return $this->respond($request, $cart, 'Coupon applied.');
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->cartOrFail($request);
        $this->carts->removeCoupon($cart);

        return $this->respond($request, $cart, 'Coupon removed.');
    }

    private function cart(Request $request, bool $create = false): ?Cart
    {
        return $this->carts->resolve($request->user('sanctum'), $request->header('X-Cart-Token'), $create);
    }

    private function cartOrFail(Request $request): Cart
    {
        return $this->cart($request) ?? abort(404, 'Cart not found.');
    }

    private function respond(Request $request, ?Cart $cart, ?string $message = null, int $status = 200): JsonResponse
    {
        $method = $request->query('shipping_method');
        $method = is_string($method) && $this->shipping->exists($method) ? $method : ShippingService::DEFAULT_METHOD;

        $summary = $this->carts->summarize($cart, $request->user('sanctum'), $method);

        return $this->success(new CartResource($summary), $message, $status);
    }
}
