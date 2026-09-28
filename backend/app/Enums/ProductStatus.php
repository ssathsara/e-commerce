<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::OutOfStock => 'Out of stock',
        };
    }

    /**
     * Statuses under which a product is visible on the public storefront.
     * Out-of-stock products stay visible (they can be wishlisted) but cannot be purchased.
     *
     * @return list<self>
     */
    public static function publiclyVisible(): array
    {
        return [self::Active, self::OutOfStock];
    }
}
