<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Credit / Debit Card',
            self::CashOnDelivery => 'Cash on Delivery',
        };
    }

    /** Whether the payment is captured online at checkout time. */
    public function isOnline(): bool
    {
        return $this === self::Card;
    }
}
