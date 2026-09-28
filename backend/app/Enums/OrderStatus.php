<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The single source of truth for allowed order status transitions.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** Whether the customer may still cancel the order themselves. */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    /** Statuses whose stock has been released back to inventory. */
    public function releasesStock(): bool
    {
        return $this === self::Cancelled;
    }
}
