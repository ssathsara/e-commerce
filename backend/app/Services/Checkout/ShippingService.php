<?php

namespace App\Services\Checkout;

use App\Exceptions\BusinessRuleException;
use App\Support\Money;

/**
 * Shipping methods and rates come from config/shop.php (driven by environment variables).
 */
class ShippingService
{
    public const DEFAULT_METHOD = 'standard';

    /**
     * @return list<array{code: string, name: string, description: string, cost: int, is_free: bool}>
     */
    public function methodsFor(int $discountedSubtotal): array
    {
        return collect(config('shop.shipping.methods'))
            ->map(fn (array $method, string $code) => [
                'code' => $code,
                'name' => $method['name'],
                'description' => $method['description'],
                'cost' => $this->cost($code, $discountedSubtotal),
                'is_free' => $this->cost($code, $discountedSubtotal) === 0,
            ])
            ->values()
            ->all();
    }

    public function exists(string $code): bool
    {
        return array_key_exists($code, config('shop.shipping.methods'));
    }

    /** Shipping cost in cents. Free-shipping threshold applies to the discounted subtotal. */
    public function cost(string $code, int $discountedSubtotal): int
    {
        $method = config("shop.shipping.methods.{$code}");

        if ($method === null) {
            throw BusinessRuleException::unprocessable('The selected shipping method is invalid.', 'shipping_method');
        }

        $threshold = Money::toCents(config('shop.shipping.free_shipping_threshold'));

        if ($method['eligible_for_free_shipping'] && $threshold > 0 && $discountedSubtotal >= $threshold) {
            return 0;
        }

        return Money::toCents($method['rate']);
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys(config('shop.shipping.methods'));
    }
}
