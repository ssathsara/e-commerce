<?php

namespace App\Services\Admin;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Back-office management of user accounts (staff and customers).
 */
class UserManagementService
{
    public function __construct(private readonly PrivilegeGuard $guard) {}

    /**
     * @param  list<string>  $roles
     */
    public function create(User $actor, array $data, array $roles): User
    {
        $this->assertCanAssign($actor, $roles);

        $user = DB::transaction(function () use ($data, $roles) {
            $user = User::create(Arr::only($data, ['name', 'email', 'phone', 'password']));
            // Accounts created by staff are trusted: the email is considered verified.
            $user->forceFill(['email_verified_at' => now(), 'is_active' => $data['is_active'] ?? true])->save();
            $user->syncRoles($roles);

            return $user;
        });

        AuditLog::info('user.created', ['user_id' => $user->id, 'roles' => $roles]);

        return $user;
    }

    /**
     * @param  list<string>|null  $roles  Null leaves roles unchanged.
     */
    public function update(User $actor, User $target, array $data, ?array $roles): User
    {
        $this->assertCanManage($actor, $target);

        if ($roles !== null) {
            if ($actor->is($target)) {
                throw new BusinessRuleException('You cannot change your own roles.', 403);
            }

            $this->assertCanAssign($actor, $roles);
            $this->assertNotRemovingLastSuperAdmin($target, $roles);
        }

        DB::transaction(function () use ($target, $data, $roles) {
            $target->fill(Arr::only($data, ['name', 'email', 'phone']));

            if (! empty($data['password'])) {
                $target->password = $data['password'];
            }

            $target->save();

            if ($roles !== null) {
                $target->syncRoles($roles);
            }
        });

        AuditLog::info('user.updated', [
            'user_id' => $target->id,
            'fields' => array_keys(Arr::except($data, ['password'])),
            'password_changed' => ! empty($data['password']),
            'roles' => $roles,
        ]);

        return $target;
    }

    public function setActive(User $actor, User $target, bool $active): User
    {
        $this->assertCanManage($actor, $target);

        if ($actor->is($target)) {
            throw new BusinessRuleException('You cannot deactivate your own account.', 403);
        }

        if (! $active) {
            $this->assertNotRemovingLastSuperAdmin($target, []);
        }

        $target->forceFill(['is_active' => $active])->save();

        if (! $active) {
            $this->terminateSessions($target);
        }

        AuditLog::info($active ? 'user.activated' : 'user.deactivated', ['user_id' => $target->id]);

        return $target;
    }

    public function delete(User $actor, User $target): void
    {
        $this->assertCanManage($actor, $target);

        if ($actor->is($target)) {
            throw new BusinessRuleException('You cannot delete your own account.', 403);
        }

        $this->assertNotRemovingLastSuperAdmin($target, []);

        // Soft delete keeps order history intact.
        $target->delete();
        $this->terminateSessions($target);

        AuditLog::info('user.deleted', ['user_id' => $target->id]);
    }

    private function assertCanManage(User $actor, User $target): void
    {
        if (! $this->guard->canManageUser($actor, $target)) {
            AuditLog::warning('security.escalation_blocked', ['action' => 'manage_user', 'target_id' => $target->id]);

            throw new BusinessRuleException('You cannot manage an account with more privileges than your own.', 403);
        }
    }

    /** @param  list<string>  $roles */
    private function assertCanAssign(User $actor, array $roles): void
    {
        if (! $this->guard->canAssignRoles($actor, $roles)) {
            AuditLog::warning('security.escalation_blocked', ['action' => 'assign_roles', 'roles' => $roles]);

            throw new BusinessRuleException('You cannot assign roles with privileges you do not have.', 403);
        }
    }

    /** @param  list<string>  $newRoles */
    private function assertNotRemovingLastSuperAdmin(User $target, array $newRoles): void
    {
        $superAdmin = RoleName::SuperAdmin->value;

        if (! $target->hasRole($superAdmin) || in_array($superAdmin, $newRoles, true)) {
            return;
        }

        $others = User::role($superAdmin)->whereKeyNot($target->id)->where('is_active', true)->count();

        if ($others === 0) {
            throw BusinessRuleException::conflict('At least one active Super Admin account must remain.');
        }
    }

    private function terminateSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->tokens()->delete();
    }
}
