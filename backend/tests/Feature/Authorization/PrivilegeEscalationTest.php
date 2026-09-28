<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    public function test_admin_cannot_create_a_super_admin(): void
    {
        $this->signIn($this->admin())->postJson('/api/v1/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'Password123!',
            'roles' => ['Super Admin'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_admin_can_create_staff_user(): void
    {
        $this->signIn($this->admin())->postJson('/api/v1/admin/users', [
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
            'password' => 'Password123!',
            'roles' => ['Staff'],
        ])->assertCreated()->assertJsonPath('data.roles', ['Staff']);

        $this->assertTrue(User::where('email', 'newstaff@example.com')->first()->hasRole('Staff'));
    }

    public function test_admin_cannot_modify_or_delete_super_admin(): void
    {
        $admin = $this->admin();
        $super = $this->superAdmin();

        $this->signIn($admin)->putJson("/api/v1/admin/users/{$super->id}", ['name' => 'Renamed', 'email' => $super->email])->assertForbidden();
        $this->signIn($admin)->patchJson("/api/v1/admin/users/{$super->id}/status", ['is_active' => false])->assertForbidden();
        $this->signIn($admin)->deleteJson("/api/v1/admin/users/{$super->id}")->assertForbidden();

        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_user_cannot_change_their_own_roles(): void
    {
        $admin = $this->admin();

        $this->signIn($admin)->putJson("/api/v1/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['Admin', 'Staff'],
        ])->assertForbidden();
    }

    public function test_users_cannot_delete_or_deactivate_themselves(): void
    {
        $super = $this->superAdmin();

        $this->signIn($super)->deleteJson("/api/v1/admin/users/{$super->id}")->assertForbidden();
        $this->signIn($super)->patchJson("/api/v1/admin/users/{$super->id}/status", ['is_active' => false])->assertForbidden();
        $this->assertNotSoftDeleted($super);
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $first = $this->superAdmin();
        $second = $this->superAdmin();

        // Demoting one of two is allowed...
        $this->signIn($first)->putJson("/api/v1/admin/users/{$second->id}", [
            'name' => $second->name, 'email' => $second->email, 'roles' => ['Admin'],
        ])->assertOk();

        // ...but a remaining Super Admin must always exist.
        $third = $this->superAdmin();
        $this->signIn($third)->putJson("/api/v1/admin/users/{$first->id}", [
            'name' => $first->name, 'email' => $first->email, 'roles' => ['Admin'],
        ])->assertOk();

        $this->assertSame(1, User::role('Super Admin')->count());
    }

    public function test_role_manager_cannot_grant_permissions_they_do_not_hold(): void
    {
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'Role Manager', 'guard_name' => 'web']);
        $role->givePermissionTo(['access admin panel', 'view roles', 'create roles', 'assign permissions', 'view products']);
        $manager->assignRole($role);

        $this->signIn($manager)->postJson('/api/v1/admin/roles', [
            'name' => 'Escalated',
            'permissions' => ['view products', 'delete users'],
        ])->assertForbidden();

        $this->signIn($manager)->postJson('/api/v1/admin/roles', [
            'name' => 'Viewer',
            'permissions' => ['view products'],
        ])->assertCreated();
    }

    public function test_super_admin_role_and_system_roles_are_protected(): void
    {
        $super = $this->superAdmin();
        $superRole = Role::findByName('Super Admin', 'web');
        $staffRole = Role::findByName('Staff', 'web');

        $this->signIn($super)->putJson("/api/v1/admin/roles/{$superRole->id}", ['permissions' => []])->assertForbidden();
        $this->signIn($super)->deleteJson("/api/v1/admin/roles/{$staffRole->id}")->assertStatus(409);
        $this->signIn($super)->putJson("/api/v1/admin/roles/{$staffRole->id}", ['name' => 'Renamed'])->assertStatus(422);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $super = $this->superAdmin();
        $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        $this->signIn($super)->deleteJson("/api/v1/admin/roles/{$role->id}")->assertStatus(409);
    }

    public function test_roles_endpoint_reports_member_counts(): void
    {
        $this->customer();
        $this->customer();

        $roles = collect($this->signIn($this->superAdmin())->getJson('/api/v1/admin/roles')->assertOk()->json('data'));

        $this->assertSame(2, $roles->firstWhere('name', 'Customer')['users_count']);
    }
}
