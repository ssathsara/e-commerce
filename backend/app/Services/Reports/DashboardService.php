<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Support\Carbon;

/**
 * Real-time dashboard figures computed from the database with aggregate queries.
 *
 * Revenue = sum of grand totals of orders whose payment status is Paid
 * (refunded orders are excluded automatically because their status becomes Refunded).
 */
class DashboardService
{
    public function summary(User $user): array
    {
        // Financial figures are only shown to users allowed to see sales reports.
        $canSeeSales = $user->can(Permissions::VIEW_SALES_REPORTS);

        $orderStatusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total_revenue' => $canSeeSales ? Money::toMajor((int) Order::query()->revenue()->sum('grand_total')) : null,
            'total_orders' => (int) $orderStatusCounts->sum(),
            'total_customers' => User::query()->customers()->count(),
            'total_products' => Product::query()->count(),
            'pending_orders' => (int) ($orderStatusCounts[OrderStatus::Pending->value] ?? 0),
            'low_stock_products' => Product::query()->lowStock()->count(),
            'out_of_stock_products' => Product::query()->outOfStock()->count(),
            'order_status_breakdown' => collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($orderStatusCounts[$status->value] ?? 0),
            ])->values(),
            'recent_orders' => Order::query()
                ->with('user:id,name,email')
                ->latest('id')
                ->limit(6)
                ->get(['id', 'order_number', 'user_id', 'status', 'payment_status', 'grand_total', 'currency', 'created_at'])
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->user?->name,
                    'status' => $order->status->value,
                    'payment_status' => $order->payment_status->value,
                    'grand_total' => Money::toMajor($order->grand_total),
                    'currency' => $order->currency,
                    'created_at' => $order->created_at?->toIso8601String(),
                ]),
            'recent_customers' => User::query()
                ->customers()
                ->latest('id')
                ->limit(6)
                ->get(['id', 'name', 'email', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                ]),
            'sales_chart' => $canSeeSales ? $this->salesChart(30) : null,
        ];
    }

    /**
     * Actionable counts for the admin header. Each item is only included when the
     * user may view the underlying module, so no data leaks through the bell.
     *
     * @return list<array{key: string, label: string, count: int, link: string}>
     */
    public function notifications(User $user): array
    {
        $items = [];

        if ($user->can(Permissions::VIEW_ORDERS)) {
            $items[] = ['key' => 'pending_orders', 'label' => 'Orders awaiting confirmation', 'count' => Order::query()->where('status', OrderStatus::Pending)->count(), 'link' => '/admin/orders?status=pending'];
        }

        if ($user->can(Permissions::VIEW_REVIEWS)) {
            $items[] = ['key' => 'pending_reviews', 'label' => 'Reviews awaiting moderation', 'count' => Review::query()->where('status', ReviewStatus::Pending)->count(), 'link' => '/admin/reviews?status=pending'];
        }

        if ($user->can(Permissions::VIEW_INVENTORY)) {
            $items[] = ['key' => 'low_stock', 'label' => 'Products low on stock', 'count' => Product::query()->lowStock()->count(), 'link' => '/admin/inventory?stock=low'];
            $items[] = ['key' => 'out_of_stock', 'label' => 'Products out of stock', 'count' => Product::query()->outOfStock()->count(), 'link' => '/admin/inventory?stock=out'];
        }

        return $items;
    }

    /**
     * Daily paid revenue and order count for the last $days days (gaps filled with zero).
     *
     * @return list<array{date: string, revenue: float, orders: int}>
     */
    public function salesChart(int $days): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();
        $to = Carbon::now()->endOfDay();
        $bucket = DateBucket::expression('created_at', 'day');

        $rows = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('payment_status', PaymentStatus::Paid)
            ->selectRaw("{$bucket} as period, SUM(grand_total) as revenue, COUNT(*) as orders")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        return array_map(fn (string $period) => [
            'date' => $period,
            'revenue' => Money::toMajor((int) ($rows[$period]->revenue ?? 0)),
            'orders' => (int) ($rows[$period]->orders ?? 0),
        ], DateBucket::periods($from, $to, 'day'));
    }
}
