<?php

namespace App\Exceptions;

class InsufficientStockException extends BusinessRuleException
{
    public static function forItem(string $name, int $available): self
    {
        $message = $available > 0
            ? "Only {$available} unit(s) of \"{$name}\" are available."
            : "\"{$name}\" is out of stock.";

        return new self($message, 409, ['stock' => [$message]]);
    }
}
