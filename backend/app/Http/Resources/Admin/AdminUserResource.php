<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User as seen by administrators. Passwords/tokens are never included.
 *
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    private bool $includePermissions = false;

    /** Include effective permissions inherited through roles (detail views). */
    public function withPermissions(): static
    {
        $this->includePermissions = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified_at !== null,
            'is_super_admin' => $this->whenLoaded('roles', fn () => $this->isSuperAdmin()),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permissions' => $this->when($this->includePermissions, fn () => $this->getAllPermissions()->pluck('name')->sort()->values()),
            'orders_count' => $this->whenCounted('orders'),
            'total_spent' => $this->whenAggregated('orders', 'grand_total', 'sum', fn ($value) => Money::toMajor((int) $value)),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
