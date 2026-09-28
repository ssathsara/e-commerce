<?php

namespace App\Services\Admin;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Prevents privilege escalation in user and role management.
 *
 * Core rule: a non-Super-Admin can only grant, assign or manage privileges they
 * themselves hold. Concretely:
 *   - the Super Admin role can only be assigned/managed by Super Admins;
 *   - a role is assignable only if all of its permissions are held by the actor;
 *   - an account can only be managed if its permissions are a subset of the actor's.
 */
class PrivilegeGuard
{
    /** @return Collection<int, Role> */
    public function assignableRoles(User $actor): Collection
    {
        $roles = Role::query()->with('permissions:id,name')->orderBy('name')->get();

        if ($actor->isSuperAdmin()) {
            return $roles;
        }

        $held = $this->permissionNames($actor);

        return $roles->reject(fn (Role $role) => $role->name === RoleName::SuperAdmin->value)
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->diff($held)->isEmpty())
            ->values();
    }

    /** @param  list<string>  $roleNames */
    public function canAssignRoles(User $actor, array $roleNames): bool
    {
        $assignable = $this->assignableRoles($actor)->pluck('name');

        return collect($roleNames)->diff($assignable)->isEmpty();
    }

    /** Whether $actor may modify/deactivate/delete $target. */
    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($target->isSuperAdmin()) {
            return false;
        }

        return $this->permissionNames($target)->diff($this->permissionNames($actor))->isEmpty();
    }

    /** @param  list<string>  $permissionNames */
    public function canGrantPermissions(User $actor, array $permissionNames): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return collect($permissionNames)->diff($this->permissionNames($actor))->isEmpty();
    }

    /** @return Collection<int, string> */
    public function permissionNames(User $user): Collection
    {
        return $user->getAllPermissions()->pluck('name');
    }
}
