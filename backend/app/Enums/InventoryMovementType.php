<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case Cancellation = 'cancellation';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Movement types an administrator may record manually.
     * Sale and Cancellation are produced only by the order workflow.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Purchase, self::Adjustment, self::Return];
    }
}
