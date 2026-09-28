<?php

namespace App\Services\Reports;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Reporting queries. Every report aggregates in SQL and paginates row-level output,
 * so large date ranges never load full datasets into memory.
 */
class ReportService
{
    /** Orders that represent real sales (excludes cancelled/refunded). */
    private const COUNTED_STATUSES = [
        OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing,
        OrderStatus::Shipped, OrderStatus::Delivered,
    ];

    public function sales(Carbon $from, Carbon $to, string $grain): array
    {
        $bucket = DateBucket::expression('created_at', $grain);

        $rows = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('payment_status', PaymentStatus::Paid)
            ->selectRaw("{$bucket} as period")
            ->selectRaw('COUNT(*) as orders, SUM(subtotal) as subtotal, SUM(discount_total) as discounts')
            ->selectRaw('SUM(shipping_total) as shipping, SUM(tax_total) as tax, SUM(grand_total) as revenue')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $series = array_map(function (string $period) use ($rows) {
            $row = $rows[$period] ?? null;
            $orders = (int) ($row->orders ?? 0);
            $revenue = (int) ($row->revenue ?? 0);

            return [
                'period' => $period,
                'orders' => $orders,
                'subtotal' => Money::toMajor((int) ($row->subtotal ?? 0)),
                'discounts' => Money::toMajor((int) ($row->discounts ?? 0)),
                'shipping' => Money::toMajor((int) ($row->shipping ?? 0)),
                'tax' => Money::toMajor((int) ($row->tax ?? 0)),
                'revenue' => Money::toMajor($revenue),
                'average_order_value' => $orders > 0 ? Money::toMajor(intdiv($revenue, $orders)) : 0,
            ];
        }, DateBucket::periods($from, $to, $grain));

        $totalOrders = array_sum(array_column($series, 'orders'));
        $totalRevenue = (int) $rows->sum('revenue');

        return [
            'series' => $series,
            'totals' => [
                'orders' => $totalOrders,
                'revenue' => Money::toMajor($totalRevenue),
                'discounts' => Money::toMajor((int) $rows->sum('discounts')),
                'tax' => Money::toMajor((int) $rows->sum('tax')),
                'shipping' => Money::toMajor((int) $rows->sum('shipping')),
                'average_order_value' => $totalOrders > 0 ? Money::toMajor(intdiv($totalRevenue, $totalOrders)) : 0,
            ],
        ];
    }

    public function ordersSummary(Carbon $from, Carbon $to): array
    {
        $base = Order::query()->whereBetween('created_at', [$from, $to]);

        return [
            'by_status' => (clone $base)->selectRaw('status, COUNT(*) as count, SUM(grand_total) as total')
                ->groupBy('status')->get()
                ->map(fn ($row) => ['status' => $row->status->value, 'count' => (int) $row->count, 'total' => Money::toMajor((int) $row->total)]),
            'by_payment_status' => (clone $base)->selectRaw('payment_status, COUNT(*) as count')
                ->groupBy('payment_status')->get()
                ->map(fn ($row) => ['payment_status' => $row->payment_status->value, 'count' => (int) $row->count]),
            'by_payment_method' => (clone $base)->selectRaw('payment_method, COUNT(*) as count')
                ->groupBy('payment_method')->get()
                ->map(fn ($row) => ['payment_method' => $row->payment_method->value, 'count' => (int) $row->count]),
        ];
    }

    public function orders(Carbon $from, Carbon $to, array $filters, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->with('user:id,name,email')
            ->withCount('items')
            ->whereBetween('created_at', [$from, $to])
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['payment_status'] ?? null, fn (Builder $q, $v) => $q->where('payment_status', $v))
            ->latest('id')
            ->paginate($perPage);
    }

    /** Best-selling products in the period (units and revenue from non-cancelled orders). */
    public function products(Carbon $from, Carbon $to, array $filters, int $perPage): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', self::COUNTED_STATUSES)
            ->when($search, function (Builder $q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('order_items.product_name', 'like', $like)->orWhere('order_items.sku', 'like', $like));
            })
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, MAX(order_items.product_name) as product_name, MAX(order_items.sku) as sku')
            ->selectRaw('SUM(order_items.quantity) as units_sold, SUM(order_items.line_total) as revenue, COUNT(DISTINCT orders.id) as orders')
            ->orderByDesc(($filters['sort'] ?? 'units') === 'revenue' ? 'revenue' : 'units_sold')
            ->paginate($perPage)
            ->through(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'sku' => $row->sku,
                'units_sold' => (int) $row->units_sold,
                'orders' => (int) $row->orders,
                'revenue' => Money::toMajor((int) $row->revenue),
            ]);
    }

    /** Customers ranked by spend in the period. */
    public function customers(Carbon $from, Carbon $to, array $filters, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->customers()
            ->search($filters['search'] ?? null)
            ->withCount(['orders as orders_count' => fn (Builder $q) => $q->whereBetween('created_at', [$from, $to])->whereIn('status', self::COUNTED_STATUSES)])
            ->withSum(['orders as total_spent' => fn (Builder $q) => $q->whereBetween('created_at', [$from, $to])->where('payment_status', PaymentStatus::Paid)], 'grand_total')
            ->when(empty($filters['include_inactive_buyers']), fn (Builder $q) => $q->whereHas('orders', fn (Builder $o) => $o->whereBetween('created_at', [$from, $to])))
            ->orderByDesc('total_spent')
            ->orderByDesc('orders_count')
            ->paginate($perPage, ['id', 'name', 'email', 'created_at'])
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'orders' => (int) $user->orders_count,
                'total_spent' => Money::toMajor((int) $user->total_spent),
                'customer_since' => $user->created_at?->toDateString(),
            ]);
    }

    public function inventorySummary(Carbon $from, Carbon $to): array
    {
        $movements = InventoryMovement::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('type, COUNT(*) as movements, SUM(quantity) as net_quantity')
            ->groupBy('type')
            ->get()
            ->keyBy(fn ($row) => $row->type->value);

        return [
            'total_units_in_stock' => (int) Product::query()->sum('stock_quantity'),
            'stock_value' => Money::toMajor((int) Product::query()->selectRaw('SUM(stock_quantity * price) as value')->value('value')),
            'low_stock_products' => Product::query()->lowStock()->count(),
            'out_of_stock_products' => Product::query()->outOfStock()->count(),
            'movements' => collect(InventoryMovementType::cases())->map(fn (InventoryMovementType $type) => [
                'type' => $type->value,
                'label' => $type->label(),
                'movements' => (int) ($movements[$type->value]->movements ?? 0),
                'net_quantity' => (int) ($movements[$type->value]->net_quantity ?? 0),
            ])->values(),
        ];
    }

    public function inventory(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()
            ->with('category:id,name')
            ->search($filters['search'] ?? null);

        match ($filters['stock'] ?? null) {
            'low' => $query->lowStock(),
            'out' => $query->outOfStock(),
            'in' => $query->inStock(),
            default => null,
        };

        return $query->orderBy('stock_quantity')->orderBy('name')
            ->paginate($perPage, ['id', 'category_id', 'name', 'sku', 'price', 'stock_quantity', 'low_stock_threshold', 'status'])
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'status' => $product->status->value,
                'stock_value' => Money::toMajor($product->stock_quantity * $product->price),
            ]);
    }
}
