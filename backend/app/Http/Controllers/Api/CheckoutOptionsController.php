<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Services\Checkout\ShippingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shipping and payment options, with shipping costs computed for the current cart.
 */
class CheckoutOptionsController extends Controller
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly CartService $carts,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $summary = $this->carts->summarize($this->carts->resolve($user, $request->header('X-Cart-Token')), $user);
        $discounted = $summary->totals->subtotal - $summary->totals->discount;

        return $this->success([
            'shipping_methods' => array_map(fn (array $method) => [
                ...$method,
                'cost' => Money::toMajor($method['cost']),
            ], $this->shipping->methodsFor($discounted)),
            'payment_methods' => array_map(fn (PaymentMethod $method) => [
                'code' => $method->value,
                'name' => $method->label(),
                'is_online' => $method->isOnline(),
            ], PaymentMethod::cases()),
            'free_shipping_threshold' => (float) config('shop.shipping.free_shipping_threshold'),
            'tax_rate' => (float) config('shop.tax_rate'),
            'currency' => config('shop.currency'),
        ]);
    }
}
