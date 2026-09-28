<?php

namespace App\Services\Catalog;

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Support\AuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Product and variant persistence. Stock is never written directly here:
 * opening stock and stock removed with deleted variants go through InventoryService
 * so the movement history always explains the current stock level.
 */
class ProductService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array  $data  Validated attributes (money already in cents), plus optional
     *                       "stock_quantity" (opening stock) and "variants".
     */
    public function create(array $data, User $actor): Product
    {
        return DB::transaction(function () use ($data, $actor) {
            $product = Product::create(Arr::except($data, ['stock_quantity', 'variants']));
            $variants = $data['variants'] ?? [];

            if ($variants === []) {
                $this->inventory->recordInitialStock($product, null, (int) ($data['stock_quantity'] ?? 0), $actor);
            } else {
                $this->syncVariants($product, $variants, $actor);
            }

            $this->inventory->syncStockStatus($product->refresh());

            AuditLog::info('product.created', ['product_id' => $product->id, 'sku' => $product->sku]);

            return $product;
        });
    }

    public function update(Product $product, array $data, User $actor): Product
    {
        return DB::transaction(function () use ($product, $data, $actor) {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->fill(Arr::except($data, ['stock_quantity', 'variants']))->save();

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants'] ?? [], $actor);
            }

            $this->inventory->syncStockStatus($product->refresh());

            AuditLog::info('product.updated', ['product_id' => $product->id, 'fields' => array_keys($data)]);

            return $product;
        });
    }

    public function setStatus(Product $product, ProductStatus $status): Product
    {
        $product->status = $status;
        $product->save();

        // Activating a product without stock lands it in OutOfStock.
        $this->inventory->syncStockStatus($product);

        AuditLog::info('product.status_changed', ['product_id' => $product->id, 'status' => $product->status->value]);

        return $product;
    }

    /** Soft delete: order history and inventory movements keep referencing the product. */
    public function delete(Product $product): void
    {
        $product->delete();

        AuditLog::info('product.deleted', ['product_id' => $product->id, 'sku' => $product->sku]);
    }

    /**
     * Create/update/delete variants to match $variants. Existing variants are matched by id;
     * their stock is only changed through inventory adjustments.
     *
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants, User $actor): void
    {
        $hadVariants = $product->variants()->exists();
        $keepIds = array_filter(array_map(fn ($v) => $v['id'] ?? null, $variants));

        // Converting a simple product into a variant product: its product-level stock is
        // superseded by variant stock, so record it leaving.
        if (! $hadVariants && $variants !== [] && $product->stock_quantity > 0) {
            $this->inventory->adjust($product, null, -$product->stock_quantity, InventoryMovementType::Adjustment, $actor, 'Stock moved to variants');
        }

        // Remove variants no longer present, recording the stock that leaves with them.
        $removed = $product->variants()->whereNotIn('id', $keepIds)->get();
        foreach ($removed as $variant) {
            if ($variant->stock_quantity > 0) {
                $this->inventory->adjust($product, $variant, -$variant->stock_quantity, InventoryMovementType::Adjustment, $actor, 'Variant removed');
            }
            $variant->delete();
        }

        foreach (array_values($variants) as $position => $attributes) {
            $fields = Arr::only($attributes, ['name', 'sku', 'options', 'price', 'compare_at_price', 'is_active']);
            $fields['sort_order'] = $attributes['sort_order'] ?? $position;

            if (! empty($attributes['id'])) {
                /** @var ProductVariant $variant */
                $variant = $product->variants()->findOrFail($attributes['id']);
                $variant->fill($fields)->save();
            } else {
                $variant = $product->variants()->create($fields);
                $this->inventory->recordInitialStock($product, $variant, (int) ($attributes['stock_quantity'] ?? 0), $actor);
            }
        }

        if ($variants === []) {
            if ($hadVariants) {
                // Variant product became a simple product: the variant stock was removed
                // above (with movements), so the product starts from zero.
                $product->stock_quantity = 0;
                $product->save();
            }
        } else {
            // Active flags may have changed: recompute the aggregate.
            $this->inventory->syncAggregateStock($product);
        }
    }
}
