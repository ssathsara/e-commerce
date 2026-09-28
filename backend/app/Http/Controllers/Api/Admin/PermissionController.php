<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * Read-only permission catalogue, grouped by module, with the roles granting each one.
 * Permissions themselves are defined in code (App\Support\Permissions) and seeded.
 */
class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()->with('roles:id,name')->orderBy('name')->get();

        $grouped = $permissions
            ->groupBy(fn (Permission $p) => Permissions::groupOf($p->name))
            ->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => $items->map(fn (Permission $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'roles' => $p->roles->pluck('name')->values(),
                ])->values(),
            ])
            ->sortBy(fn ($group) => array_search($group['group'], array_keys(Permissions::grouped()), true))
            ->values();

        return $this->success($grouped);
    }
}
