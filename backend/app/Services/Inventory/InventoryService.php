<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY code path that changes stock levels.
 *
 * Every change:
 *   1. runs in a transaction and locks the affected product (then variant) row,
 *   2. refuses to take stock below zero,
 *   3. records an inventory movement,
 *   4. keeps the product's aggregate stock and in/out-of-stock status in sync.
 *
 * Lock ordering is always product -> variant, which callers locking several
 * products must also follow (sorted by product id) to avoid deadlocks.
 */
class InventoryService
{
    public function adjust(
        Product $product,
        ?ProductVariant $variant,
        int $quantityChange,
        InventoryMovementType $type,
        ?User $actor = null,
        ?string $note = null,
        ?Order $order = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $variant, $quantityChange, $type, $actor, $note, $order) {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::withTrashed()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            if ($variant !== null) {
                /** @var ProductVariant $target */
                $target = ProductVariant::query()
                    ->whereKey($variant->getKey())
                    ->where('product_id', $lockedProduct->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            } else {
                $target = $lockedProduct;
            }

            $before = $target->stock_quantity;
            $after = $before + $quantityChange;

            if ($after < 0) {
                $name = $variant ? "{$lockedProduct->name} ({$target->name})" : $lockedProduct->name;

                throw InsufficientStockException::forItem($name, $before);
            }

            $target->stock_quantity = $after;
            $target->save();

            if ($variant !== null) {
                $this->syncAggregateStock($lockedProduct);
            }

            $this->syncStockStatus($lockedProduct);

            $movement = InventoryMovement::create([
                'product_id' => $lockedProduct->id,
                'product_variant_id' => $variant?->getKey(),
                'type' => $type,
                'quantity' => $quantityChange,
                'stock_before' => $before,
                'stock_after' => $after,
                'order_id' => $order?->getKey(),
                'user_id' => $actor?->getKey(),
                'note' => $note,
            ]);

            // Keep callers' in-memory models consistent with the database.
            $product->stock_quantity = $lockedProduct->stock_quantity;
            $product->status = $lockedProduct->status;
            if ($variant !== null) {
                $variant->stock_quantity = $after;
            }

            if (in_array($type, InventoryMovementType::manual(), true)) {
                AuditLog::info('inventory.adjusted', [
                    'product_id' => $lockedProduct->id,
                    'variant_id' => $variant?->getKey(),
                    'type' => $type->value,
                    'change' => $quantityChange,
                    'stock_after' => $after,
                ]);
            }

            return $movement;
        });
    }

    /**
     * Records the opening stock of a newly created product or variant as a Purchase movement,
     * so that the movement history always explains the current stock level.
     */
    public function recordInitialStock(Product $product, ?ProductVariant $variant, int $quantity, ?User $actor): void
    {
        if ($quantity > 0) {
            $this->adjust($product, $variant, $quantity, InventoryMovementType::Purchase, $actor, 'Initial stock');
        }
    }

    /**
     * For products with variants, the product stock is the sum of its active variants.
     * Must be called with the product row locked (or inside the same transaction).
     */
    public function syncAggregateStock(Product $product): void
    {
        if (! $product->variants()->exists()) {
            return;
        }

        $product->stock_quantity = (int) $product->variants()->where('is_active', true)->sum('stock_quantity');
        $product->save();
    }

    /** Moves Active <-> OutOfStock automatically. Draft/Inactive are never changed. */
    public function syncStockStatus(Product $product): void
    {
        if ($product->status === ProductStatus::Active && $product->stock_quantity <= 0) {
            $product->status = ProductStatus::OutOfStock;
        } elseif ($product->status === ProductStatus::OutOfStock && $product->stock_quantity > 0) {
            $product->status = ProductStatus::Active;
        }

        if ($product->isDirty('status')) {
            $product->save();
        }
    }
}
