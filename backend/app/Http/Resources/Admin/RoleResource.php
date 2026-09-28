<?php

namespace App\Http\Resources\Admin;

use App\Enums\RoleName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Spatie\Permission\Models\Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_system' => RoleName::isSystemRole($this->name),
            'is_super_admin' => $this->name === RoleName::SuperAdmin->value,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->sort()->values()),
            'users_count' => $this->when(isset($this->users_count), fn () => (int) $this->users_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
