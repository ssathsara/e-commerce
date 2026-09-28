<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Support\Permissions;

/**
 * Resource-level rules for orders. Super Admins pass via Gate::before.
 */
class OrderPolicy
{
    /** Owners see their own orders; staff with "view orders" see any order. */
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->can(Permissions::VIEW_ORDERS);
    }

    /** Only the owner may pay for or cancel their order through the customer API. */
    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
