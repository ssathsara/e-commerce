<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    public function test_coupon_crud_toggle_and_usage(): void
    {
        $admin = $this->admin();

        $id = $this->signIn($admin)->postJson('/api/v1/admin/coupons', [
            'code' => 'spring15',
            'type' => 'percentage',
            'value' => 15,
            'max_discount_amount' => 30,
            'expires_at' => now()->addMonth()->toIso8601String(),
            'usage_limit' => 100,
        ])->assertCreated()->assertJsonPath('data.code', 'SPRING15')->assertJsonPath('data.value', 15)->json('data.id');

        $this->assertSame(3000, Coupon::find($id)->max_discount_amount);

        $this->signIn($admin)->patchJson("/api/v1/admin/coupons/{$id}/toggle")->assertOk()->assertJsonPath('data.is_active', false);
        $this->signIn($admin)->getJson("/api/v1/admin/coupons/{$id}/usages")->assertOk()->assertJsonPath('meta.total', 0);
        $this->signIn($admin)->deleteJson("/api/v1/admin/coupons/{$id}")->assertOk();
        $this->assertSoftDeleted('coupons', ['id' => $id]);
    }

    public function test_coupon_validation(): void
    {
        $this->signIn($this->admin())->postJson('/api/v1/admin/coupons', [
            'code' => 'bad code!',
            'type' => 'percentage',
            'value' => 150,
            'expires_at' => now()->subDay()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors(['code', 'value', 'expires_at']);
    }

    public function test_customer_management_and_deactivation(): void
    {
        $admin = $this->admin();

        $customerId = $this->signIn($admin)->postJson('/api/v1/admin/customers', [
            'name' => 'Walk-in Customer',
            'email' => 'walkin@example.com',
            'password' => 'Password123!',
        ])->assertCreated()->assertJsonPath('data.roles', ['Customer'])->json('data.id');

        $this->signIn($admin)->getJson('/api/v1/admin/customers?search=walk-in')->assertJsonPath('meta.total', 1);
        $this->signIn($admin)->getJson("/api/v1/admin/customers/{$customerId}")->assertOk()->assertJsonStructure(['data' => ['addresses', 'recent_orders']]);
        $this->signIn($admin)->patchJson("/api/v1/admin/customers/{$customerId}/status", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        // Staff accounts are not reachable through the customers module.
        $this->signIn($admin)->getJson("/api/v1/admin/customers/{$this->staff()->id}")->assertNotFound();
    }

    public function test_reports_return_real_aggregates(): void
    {
        $customer = $this->customer();
        $this->addToCart($customer, $this->product(2000), 2);
        $this->checkout($customer)->assertCreated();
        $admin = $this->admin();

        $this->signIn($admin)->getJson('/api/v1/admin/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.totals.orders', 1)
            ->assertJsonPath('data.totals.revenue', 49); // 40 + 5 shipping + 4 tax

        $this->signIn($admin)->getJson('/api/v1/admin/reports/products')->assertOk()->assertJsonPath('data.0.units_sold', 2);
        $this->signIn($admin)->getJson('/api/v1/admin/reports/customers')->assertOk()->assertJsonPath('data.0.orders', 1);
        $this->signIn($admin)->getJson('/api/v1/admin/reports/orders')->assertOk()->assertJsonPath('meta.total', 1);
        $this->signIn($admin)->getJson('/api/v1/admin/reports/inventory?stock=in')->assertOk()->assertJsonStructure(['meta' => ['summary' => ['movements']]]);
        $this->signIn($admin)->getJson('/api/v1/admin/reports/sales?from=2026-02-01&to=2026-01-01')->assertStatus(422);
    }

    public function test_dashboard_and_notifications_use_live_data(): void
    {
        $customer = $this->customer();
        $this->addToCart($customer, $this->product(1000));
        $this->checkout($customer, ['payment_method' => 'cash_on_delivery', 'payment_token' => null]);

        $admin = $this->admin();
        $this->signIn($admin)->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.pending_orders', 1)
            ->assertJsonCount(30, 'data.sales_chart');

        $notifications = collect($this->signIn($admin)->getJson('/api/v1/admin/notifications')->json('data'))->keyBy('key');
        $this->assertSame(1, $notifications['pending_orders']['count']);
    }
}
