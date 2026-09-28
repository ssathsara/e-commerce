<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Backend enforcement of Spatie permissions on admin endpoints, independent of the UI.
 */
class AdminAccessTest extends TestCase
{
    public static function adminEndpoints(): array
    {
        return [
            'dashboard' => ['GET', '/api/v1/admin/dashboard'],
            'products' => ['GET', '/api/v1/admin/products'],
            'orders' => ['GET', '/api/v1/admin/orders'],
            'customers' => ['GET', '/api/v1/admin/customers'],
            'inventory' => ['GET', '/api/v1/admin/inventory'],
            'users' => ['GET', '/api/v1/admin/users'],
            'roles' => ['GET', '/api/v1/admin/roles'],
            'reports' => ['GET', '/api/v1/admin/reports/sales'],
        ];
    }

    #[DataProvider('adminEndpoints')]
    public function test_guest_receives_401(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('adminEndpoints')]
    public function test_customer_receives_403(string $method, string $uri): void
    {
        $this->signIn($this->customer())->json($method, $uri)
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You do not have permission to perform this action.');
    }

    #[DataProvider('adminEndpoints')]
    public function test_super_admin_has_unrestricted_access(string $method, string $uri): void
    {
        $this->signIn($this->superAdmin())->json($method, $uri)->assertOk();
    }

    public function test_staff_can_view_but_not_create_or_delete_products(): void
    {
        $staff = $this->staff();
        $product = $this->product();

        $this->signIn($staff)->getJson('/api/v1/admin/products')->assertOk();
        $this->signIn($staff)->getJson("/api/v1/admin/products/{$product->id}")->assertOk();
        $this->signIn($staff)->postJson('/api/v1/admin/products', [])->assertForbidden();
        $this->signIn($staff)->deleteJson("/api/v1/admin/products/{$product->id}")->assertForbidden();

        $this->assertNotSoftDeleted($product);
    }

    public function test_staff_cannot_manage_users_roles_or_view_reports(): void
    {
        $staff = $this->staff();

        $this->signIn($staff)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->signIn($staff)->postJson('/api/v1/admin/roles', ['name' => 'Hacker'])->assertForbidden();
        $this->signIn($staff)->getJson('/api/v1/admin/reports/orders')->assertForbidden();
        $this->signIn($staff)->getJson('/api/v1/admin/reports/sales')->assertForbidden();
    }

    public function test_staff_can_update_orders_but_not_cancel_them(): void
    {
        $customer = $this->customer();
        $this->addToCart($customer, $this->product());
        $orderId = $this->checkout($customer)->json('data.id');

        $staff = $this->staff();
        $this->signIn($staff)->patchJson("/api/v1/admin/orders/{$orderId}/status", ['status' => 'cancelled'])->assertForbidden();
        $this->signIn($staff)->patchJson("/api/v1/admin/orders/{$orderId}/status", ['status' => 'processing'])->assertOk();
    }

    public function test_admin_permissions_allow_catalogue_management(): void
    {
        $admin = $this->admin();

        $this->signIn($admin)->postJson('/api/v1/admin/brands', ['name' => 'Acme'])->assertCreated();
        $this->signIn($admin)->getJson('/api/v1/admin/users')->assertOk();
        // Admin role is not allowed to create roles in the default configuration.
        $this->signIn($admin)->postJson('/api/v1/admin/roles', ['name' => 'Editors'])->assertForbidden();
    }

    public function test_permission_granted_directly_to_custom_role_is_honoured(): void
    {
        $user = User::factory()->create();
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Catalogue Viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(['access admin panel', 'view products']);
        $user->assignRole($role);

        $this->signIn($user)->getJson('/api/v1/admin/products')->assertOk();
        $this->signIn($user)->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    public function test_dashboard_hides_revenue_without_sales_report_permission(): void
    {
        $this->signIn($this->staff())->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_revenue', null)
            ->assertJsonPath('data.sales_chart', null);

        $this->signIn($this->admin())->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_revenue', 0);
    }
}
