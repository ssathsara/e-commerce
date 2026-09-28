<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Exceptions\InsufficientStockException;
use App\Services\Inventory\InventoryService;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    public function test_manual_adjustment_creates_movement_and_updates_stock(): void
    {
        $admin = $this->admin();
        $product = $this->product(stock: 5);

        $this->signIn($admin)->postJson('/api/v1/admin/inventory/adjustments', [
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 20,
            'note' => 'PO-1234',
        ])->assertCreated()->assertJsonPath('data.stock_before', 5)->assertJsonPath('data.stock_after', 25);

        $this->assertSame(25, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'user_id' => $admin->id, 'type' => 'purchase', 'quantity' => 20, 'note' => 'PO-1234']);
    }

    public function test_stock_cannot_become_negative(): void
    {
        $product = $this->product(stock: 3);

        $this->signIn($this->admin())->postJson('/api/v1/admin/inventory/adjustments', [
            'product_id' => $product->id, 'type' => 'adjustment', 'quantity' => -4, 'note' => 'Damaged',
        ])->assertStatus(409);

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseMissing('inventory_movements', ['product_id' => $product->id, 'quantity' => -4]);

        $this->expectException(InsufficientStockException::class);
        app(InventoryService::class)->adjust($product, null, -10, InventoryMovementType::Adjustment);
    }

    public function test_adjustment_rules_are_validated(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->signIn($admin)->postJson('/api/v1/admin/inventory/adjustments', ['product_id' => $product->id, 'type' => 'purchase', 'quantity' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->signIn($admin)->postJson('/api/v1/admin/inventory/adjustments', ['product_id' => $product->id, 'type' => 'adjustment', 'quantity' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('note');
        // Sales and cancellations can only come from orders.
        $this->signIn($admin)->postJson('/api/v1/admin/inventory/adjustments', ['product_id' => $product->id, 'type' => 'sale', 'quantity' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('type');
        $this->signIn($admin)->postJson('/api/v1/admin/inventory/adjustments', ['product_id' => $product->id, 'type' => 'purchase', 'quantity' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_variant_stock_is_adjusted_per_variant_and_aggregated(): void
    {
        $admin = $this->admin();
        $product = $this->variantProduct([2, 3]);
        $variant = $product->variants()->first();

        $this->signIn($admin)->postJson('/api/v1/admin/inventory/adjustments', ['product_id' => $product->id, 'type' => 'purchase', 'quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('variant_id');

        $this->signIn($admin)->postJson('/api/v1/admin/inventory/adjustments', ['product_id' => $product->id, 'variant_id' => $variant->id, 'type' => 'purchase', 'quantity' => 5])
            ->assertCreated();

        $this->assertSame(7, $variant->fresh()->stock_quantity);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_status_switches_between_active_and_out_of_stock_automatically(): void
    {
        $service = app(InventoryService::class);
        $product = $this->product(stock: 1);

        $service->adjust($product, null, -1, InventoryMovementType::Adjustment, note: 'test');
        $this->assertSame(ProductStatus::OutOfStock, $product->fresh()->status);

        $service->adjust($product->fresh(), null, 4, InventoryMovementType::Purchase);
        $this->assertSame(ProductStatus::Active, $product->fresh()->status);

        // Draft products are never activated by restocking.
        $draft = $this->product(stock: 0, attributes: ['status' => ProductStatus::Draft]);
        $service->adjust($draft, null, 4, InventoryMovementType::Purchase);
        $this->assertSame(ProductStatus::Draft, $draft->fresh()->status);
    }

    public function test_inventory_listing_filters_and_history(): void
    {
        $admin = $this->admin();
        $this->product(stock: 0, attributes: ['name' => 'Empty', 'status' => ProductStatus::OutOfStock]);
        $low = $this->product(stock: 2, attributes: ['name' => 'Nearly gone', 'low_stock_threshold' => 5]);
        $this->product(stock: 50, attributes: ['name' => 'Plenty']);

        $this->signIn($admin)->getJson('/api/v1/admin/inventory?stock=low')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Nearly gone')
            ->assertJsonPath('meta.low_stock_count', 1)->assertJsonPath('meta.out_of_stock_count', 1);

        app(InventoryService::class)->adjust($low, null, 3, InventoryMovementType::Return, $admin, 'Customer return');
        $this->signIn($admin)->getJson("/api/v1/admin/inventory/movements?product_id={$low->id}")
            ->assertOk()->assertJsonPath('data.0.type', 'return')->assertJsonPath('data.0.user', $admin->name);
    }

    public function test_staff_can_manage_inventory_but_customers_cannot(): void
    {
        $product = $this->product();
        $payload = ['product_id' => $product->id, 'type' => 'purchase', 'quantity' => 1];

        $this->signIn($this->staff())->postJson('/api/v1/admin/inventory/adjustments', $payload)->assertCreated();
        $this->signIn($this->customer())->postJson('/api/v1/admin/inventory/adjustments', $payload)->assertForbidden();
    }
}
