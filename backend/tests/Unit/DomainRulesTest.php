<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Services\Checkout\ShippingService;
use App\Services\Checkout\TotalsCalculator;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainRulesTest extends TestCase
{
    public static function transitions(): array
    {
        return [
            [OrderStatus::Pending, OrderStatus::Confirmed, true],
            [OrderStatus::Pending, OrderStatus::Shipped, false],
            [OrderStatus::Confirmed, OrderStatus::Processing, true],
            [OrderStatus::Processing, OrderStatus::Shipped, true],
            [OrderStatus::Shipped, OrderStatus::Cancelled, false],
            [OrderStatus::Shipped, OrderStatus::Delivered, true],
            [OrderStatus::Delivered, OrderStatus::Refunded, true],
            [OrderStatus::Cancelled, OrderStatus::Pending, false],
            [OrderStatus::Refunded, OrderStatus::Delivered, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_order_state_machine(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_payment_status_is_independent_of_order_status(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Paid));
        $this->assertFalse(PaymentStatus::Refunded->canTransitionTo(PaymentStatus::Paid));
        $this->assertNotSame(array_map(fn ($c) => $c->value, OrderStatus::cases()), array_map(fn ($c) => $c->value, PaymentStatus::cases()));
    }

    public function test_money_conversion_avoids_float_errors(): void
    {
        $this->assertSame(1999, Money::toCents('19.99'));
        $this->assertSame(30, Money::toCents(0.1 + 0.2));
        $this->assertSame(19.99, Money::toMajor(1999));
        $this->assertSame(250, Money::percentOf(2499, 10));
    }

    public function test_totals_calculator(): void
    {
        $calculator = new TotalsCalculator(new ShippingService);
        $coupon = Coupon::factory()->percentage(10)->make();

        $totals = $calculator->calculate(10000, $coupon, 'standard');

        // 100.00 - 10.00 = 90.00 (< 100 threshold => $5 shipping), tax 10% of 90 = 9.00
        $this->assertSame([10000, 1000, 500, 900, 10400], [$totals->subtotal, $totals->discount, $totals->shipping, $totals->tax, $totals->total]);
        $this->assertSame(0, $calculator->calculate(0, null, 'express')->total);
    }
}
