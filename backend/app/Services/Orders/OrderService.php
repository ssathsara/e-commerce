<?php

namespace App\Services\Orders;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Coupons\CouponService;
use App\Services\Inventory\InventoryService;
use App\Services\Payments\PaymentService;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Order lifecycle after placement. All status changes go through transition(),
 * which enforces OrderStatus::allowedTransitions().
 */
class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CouponService $coupons,
        private readonly PaymentService $payments,
    ) {}

    /**
     * @param  array{tracking_number?: string|null, note?: string|null}  $context
     */
    public function transition(Order $order, OrderStatus $next, ?User $actor, array $context = []): Order
    {
        return DB::transaction(function () use ($order, $next, $actor, $context) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if (! $previous->canTransitionTo($next)) {
                throw BusinessRuleException::conflict(
                    "Order status cannot change from {$previous->label()} to {$next->label()}.",
                    ['status' => ["Allowed: ".($this->allowedLabels($previous) ?: 'none').'.']],
                );
            }

            match ($next) {
                OrderStatus::Confirmed => $this->guardConfirmation($locked),
                OrderStatus::Shipped => $this->applyShipment($locked, $context['tracking_number'] ?? null),
                OrderStatus::Delivered => $locked->delivered_at = now(),
                OrderStatus::Cancelled => $this->applyCancellation($locked, $actor),
                OrderStatus::Refunded => $this->payments->refund($locked),
                default => null,
            };

            if ($next === OrderStatus::Confirmed) {
                $locked->confirmed_at = now();
            }

            $locked->status = $next;
            $locked->save();

            AuditLog::info('order.status_changed', [
                'order_id' => $locked->id,
                'from' => $previous->value,
                'to' => $next->value,
            ]);

            event(new OrderStatusChanged($locked, $previous));

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }

    /** Customers may cancel their own orders until they are being processed. */
    public function cancelByCustomer(Order $order, User $customer): Order
    {
        if (! $order->status->isCancellableByCustomer()) {
            throw BusinessRuleException::conflict('This order can no longer be cancelled. Please contact support.');
        }

        return $this->transition($order, OrderStatus::Cancelled, $customer);
    }

    /** Update admin-editable, non-status fields. */
    public function updateDetails(Order $order, array $attributes): Order
    {
        $order->fill($attributes)->save();

        AuditLog::info('order.details_updated', ['order_id' => $order->id, 'fields' => array_keys($attributes)]);

        return $order;
    }

    private function guardConfirmation(Order $order): void
    {
        if ($order->payment_method === PaymentMethod::Card && $order->payment_status !== PaymentStatus::Paid) {
            throw BusinessRuleException::conflict('Card orders can only be confirmed once payment has been received.');
        }
    }

    private function applyShipment(Order $order, ?string $trackingNumber): void
    {
        $order->shipped_at = now();

        if ($trackingNumber !== null && $trackingNumber !== '') {
            $order->tracking_number = $trackingNumber;
        }
    }

    /**
     * Returns stock, gives back the coupon usage, reverses sales counters and refunds
     * any captured payment. Runs inside the caller's transaction.
     */
    private function applyCancellation(Order $order, ?User $actor): void
    {
        $order->loadMissing(['items.product', 'items.variant']);

        foreach ($order->items->sortBy('product_id') as $item) {
            if ($item->product === null) {
                continue; // Product permanently removed; nothing to restock.
            }

            $this->inventory->adjust(
                $item->product,
                $item->variant,
                $item->quantity,
                InventoryMovementType::Cancellation,
                $actor,
                "Order {$order->order_number} cancelled",
                $order,
            );

            Product::query()->whereKey($item->product_id)->where('sales_count', '>=', $item->quantity)
                ->decrement('sales_count', $item->quantity);
        }

        $this->coupons->releaseUsage($order);

        if ($order->payment_status === PaymentStatus::Paid) {
            $this->payments->refund($order);
        } else {
            // Nothing was captured: close any open payment attempt. The order keeps its
            // payment status (e.g. Pending) because no payment actually failed.
            $order->payments()->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'Order cancelled']);
        }

        $order->cancelled_at = now();
    }

    private function allowedLabels(OrderStatus $status): string
    {
        return implode(', ', array_map(fn (OrderStatus $s) => $s->label(), $status->allowedTransitions()));
    }
}
