<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user with their roles and effective permissions.
 * The SPA uses these ONLY to decide what to display; the API enforces everything.
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permissions = $this->isSuperAdmin()
            ? Permissions::all()
            : $this->getAllPermissions()->pluck('name')->values()->all();

        return [
            'user' => (new UserResource($this->resource))->resolve($request),
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $permissions,
        ];
    }
}
