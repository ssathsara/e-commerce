<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Real-time dashboard figures computed from the database with aggregate queries.
 *
 * Revenue = sum of grand totals of orders whose payment status is Paid
 * (refunded orders are excluded automatically because their status becomes Refunded).
 */
class DashboardService
{
    public function summary(): array
    {
        $orderStatusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total_revenue' => Money::toMajor((int) Order::query()->revenue()->sum('grand_total')),
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
            'sales_chart' => $this->salesChart(30),
        ];
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
