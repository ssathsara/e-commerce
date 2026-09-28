<?php

namespace App\Http\Resources\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Order representation for the admin portal: adds customer details, internal notes
 * and the status transitions currently allowed (so the UI only offers valid actions).
 *
 * @mixin Order
 */
class AdminOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'customer' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'admin_notes' => $this->admin_notes,
            'allowed_transitions' => array_map(fn (OrderStatus $s) => $s->value, $this->status->allowedTransitions()),
            'allowed_payment_transitions' => $this->payment_method->isOnline()
                ? []
                : array_map(fn (PaymentStatus $s) => $s->value, $this->payment_status->allowedTransitions()),
        ]);
    }
}
