<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by services when a request is well-formed but violates a business rule
 * (insufficient stock, invalid status transition, coupon not applicable, ...).
 *
 * Rendered by bootstrap/app.php as:
 *   { "success": false, "message": "...", "errors": { ... } }
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  Optional field-keyed errors (same shape as validation errors).
     */
    public function __construct(
        string $message,
        protected int $status = 422,
        protected array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $message, array $errors = []): static
    {
        return new static($message, 409, $errors);
    }

    public static function unprocessable(string $message, ?string $field = null): static
    {
        return new static($message, 422, $field ? [$field => [$message]] : []);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
