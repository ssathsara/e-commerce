<?php

namespace Tests\Feature\Orders;

use App\Enums\InventoryMovementType;
use App\Events\OrderStatusChanged;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    private function placeOrder(?User $customer = null, array $overrides = [], int $stock = 10): array
    {
        $customer ??= $this->customer();
        $product = $this->product(2000, $stock);
        $this->addToCart($customer, $product, 2);
        $order = Order::findOrFail($this->checkout($customer, $overrides)->assertCreated()->json('data.id'));

        return [$customer, $order, $product];
    }

    public function test_customer_can_list_and_view_own_orders_only(): void
    {
        [$customer, $order] = $this->placeOrder();
        [, $otherOrder] = $this->placeOrder();

        $this->signIn($customer)->getJson('/api/v1/orders')->assertOk()->assertJsonPath('meta.total', 1);
        $this->signIn($customer)->getJson("/api/v1/orders/{$order->id}")->assertOk();
        $this->signIn($customer)->getJson("/api/v1/orders/{$otherOrder->id}")->assertForbidden();
        $this->signIn($customer)->postJson("/api/v1/orders/{$otherOrder->id}/cancel")->assertForbidden();
    }

    public function test_staff_with_view_orders_can_view_any_order(): void
    {
        [, $order] = $this->placeOrder();

        $this->signIn($this->staff())->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.id', $order->user_id)
            ->assertJsonPath('data.allowed_transitions', ['processing', 'cancelled']);
    }

    public function test_customer_cancellation_restocks_releases_coupon_and_refunds(): void
    {
        Coupon::factory()->percentage(10)->create(['code' => 'TENOFF']);
        $customer = $this->customer();
        $product = $this->product(2000, 10);
        $this->addToCart($customer, $product, 2);
        $this->signIn($customer)->postJson('/api/v1/cart/coupon', ['coupon_code' => 'TENOFF']);
        $orderId = $this->checkout($customer)->json('data.id');
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $this->signIn($customer)->postJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment_status', 'refunded');

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(0, $product->fresh()->sales_count);
        $this->assertDatabaseHas('inventory_movements', ['order_id' => $orderId, 'type' => InventoryMovementType::Cancellation->value, 'quantity' => 2]);
        $this->assertDatabaseMissing('coupon_usages', ['order_id' => $orderId]);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'status' => 'refunded']);
    }

    public function test_customer_cannot_cancel_after_processing_started(): void
    {
        [$customer, $order] = $this->placeOrder();
        $this->signIn($this->admin())->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])->assertOk();

        $this->signIn($customer)->postJson("/api/v1/orders/{$order->id}/cancel")->assertStatus(409);
    }

    public function test_admin_follows_valid_fulfilment_flow_and_customer_is_notified(): void
    {
        Notification::fake();
        [$customer, $order] = $this->placeOrder();
        $admin = $this->admin();

        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])->assertOk();
        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'shipped', 'tracking_number' => '1Z999'])
            ->assertOk()->assertJsonPath('data.tracking_number', '1Z999');
        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk()->assertJsonPath('data.status', 'delivered');

        $this->assertNotNull($order->fresh()->delivered_at);
        Notification::assertSentTo($customer, OrderStatusChangedNotification::class, 4); // confirmed (payment) + 3 changes
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        [, $order] = $this->placeOrder();
        $admin = $this->admin();

        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Order status cannot change from Confirmed to Delivered.');

        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertOk();
        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])->assertStatus(409);
        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'not-a-status'])->assertStatus(422);
    }

    public function test_unpaid_card_order_cannot_be_confirmed(): void
    {
        [, $order] = $this->placeOrder(overrides: ['payment_token' => 'tok_chargeDeclined']);

        $this->signIn($this->admin())->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'confirmed'])->assertStatus(409);
    }

    public function test_refund_after_delivery_refunds_payment(): void
    {
        [, $order] = $this->placeOrder();
        $admin = $this->admin();

        foreach (['processing', 'shipped', 'delivered', 'refunded'] as $status) {
            $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])->assertOk();
        }

        $this->assertSame('refunded', $order->fresh()->payment_status->value);
    }

    public function test_cash_on_delivery_payment_status_is_managed_manually(): void
    {
        [, $order] = $this->placeOrder(overrides: ['payment_method' => 'cash_on_delivery', 'payment_token' => null]);
        $admin = $this->admin();

        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/payment-status", ['payment_status' => 'paid'])
            ->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.status', 'confirmed');

        $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/payment-status", ['payment_status' => 'pending'])->assertStatus(409);
    }

    public function test_card_payment_status_cannot_be_changed_manually(): void
    {
        [, $order] = $this->placeOrder();

        $this->signIn($this->admin())->patchJson("/api/v1/admin/orders/{$order->id}/payment-status", ['payment_status' => 'refunded'])
            ->assertStatus(409);
    }

    public function test_admin_order_list_filters_and_search(): void
    {
        [$customer, $order] = $this->placeOrder();
        $this->placeOrder(overrides: ['payment_method' => 'cash_on_delivery', 'payment_token' => null]);

        $this->signIn($this->admin())->getJson('/api/v1/admin/orders?status=confirmed')->assertJsonPath('meta.total', 1);
        $this->signIn($this->admin())->getJson('/api/v1/admin/orders?payment_status=pending')->assertJsonPath('meta.total', 1);
        $this->signIn($this->admin())->getJson('/api/v1/admin/orders?search='.$order->order_number)
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.customer.email', $customer->email);
    }

    public function test_status_change_event_is_dispatched_after_commit(): void
    {
        \Illuminate\Support\Facades\Event::fake([OrderStatusChanged::class]);
        [, $order] = $this->placeOrder();

        $this->signIn($this->admin())->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing']);

        \Illuminate\Support\Facades\Event::assertDispatched(OrderStatusChanged::class, fn ($e) => $e->order->id === $order->id && $e->previous->value === 'confirmed');
    }
}
