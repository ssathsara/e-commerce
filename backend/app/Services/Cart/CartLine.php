<?php

namespace App\Services\Cart;

use App\Models\CartItem;

final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public int $unitPrice,
        public int $lineTotal,
        public int $availableStock,
        public ?string $issue,
    ) {}
}
