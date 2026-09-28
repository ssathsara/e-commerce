<?php

namespace App\Services\Admin;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Role management on top of Spatie Permission (the single source of truth).
 */
class RoleService
{
    public function __construct(private readonly PrivilegeGuard $guard) {}

    /** @param  list<string>  $permissions */
    public function create(User $actor, string $name, array $permissions): Role
    {
        if (RoleName::isSystemRole($name)) {
            throw BusinessRuleException::unprocessable('This role name is reserved.', 'name');
        }

        $this->assertCanGrant($actor, $permissions);

        $role = DB::transaction(function () use ($name, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            return $role;
        });

        AuditLog::info('role.created', ['role' => $name, 'permissions' => $permissions]);

        return $role;
    }

    /**
     * @param  list<string>|null  $permissions  Null leaves permissions unchanged.
     */
    public function update(User $actor, Role $role, ?string $name, ?array $permissions): Role
    {
        $this->assertEditable($actor, $role);
        $role->loadMissing('permissions');

        if ($name !== null && $name !== $role->name) {
            if (RoleName::isSystemRole($role->name)) {
                throw BusinessRuleException::unprocessable('Built-in roles cannot be renamed.', 'name');
            }

            if (RoleName::isSystemRole($name)) {
                throw BusinessRuleException::unprocessable('This role name is reserved.', 'name');
            }
        }

        if ($permissions !== null) {
            // Only permissions the actor holds may be added or removed.
            $changed = collect($permissions)
                ->diff($role->permissions->pluck('name'))
                ->merge($role->permissions->pluck('name')->diff($permissions))
                ->values()
                ->all();

            $this->assertCanGrant($actor, $changed);
        }

        DB::transaction(function () use ($role, $name, $permissions) {
            if ($name !== null) {
                $role->name = $name;
                $role->save();
            }

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }
        });

        AuditLog::info('role.updated', ['role_id' => $role->id, 'name' => $role->name, 'permissions' => $permissions]);

        return $role->load('permissions');
    }

    public function delete(User $actor, Role $role): void
    {
        if (RoleName::isSystemRole($role->name)) {
            throw BusinessRuleException::conflict('Built-in roles cannot be deleted.');
        }

        $this->assertEditable($actor, $role);

        $userCount = User::role($role->name)->count();
        if ($userCount > 0) {
            throw BusinessRuleException::conflict("This role is assigned to {$userCount} user(s). Reassign them first.");
        }

        $role->delete();

        AuditLog::info('role.deleted', ['role' => $role->name]);
    }

    private function assertEditable(User $actor, Role $role): void
    {
        if ($role->name === RoleName::SuperAdmin->value) {
            // Super Admin is granted everything via Gate::before; its permission list is irrelevant.
            throw new BusinessRuleException('The Super Admin role cannot be modified.', 403);
        }

        if (! $actor->isSuperAdmin() && $actor->hasRole($role->name)) {
            AuditLog::warning('security.escalation_blocked', ['action' => 'edit_own_role', 'role' => $role->name]);

            throw new BusinessRuleException('You cannot modify a role that you hold.', 403);
        }
    }

    /** @param  list<string>  $permissions */
    private function assertCanGrant(User $actor, array $permissions): void
    {
        if (! $this->guard->canGrantPermissions($actor, $permissions)) {
            AuditLog::warning('security.escalation_blocked', ['action' => 'grant_permissions', 'permissions' => $permissions]);

            throw new BusinessRuleException('You cannot grant or revoke permissions you do not have.', 403);
        }
    }
}
