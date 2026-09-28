<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderDetailsRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Requests\Admin\PaymentStatusRequest;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Models\Order;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'customer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:newest,oldest,total_desc,total_asc'],
        ]);

        $orders = Order::query()
            ->with('user:id,name,email')
            ->withCount('items')
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->query('payment_method'), fn ($q, $v) => $q->where('payment_method', $v))
            ->when($request->query('customer_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'));

        match ($request->query('sort')) {
            'oldest' => $orders->oldest('id'),
            'total_desc' => $orders->orderByDesc('grand_total'),
            'total_asc' => $orders->orderBy('grand_total'),
            default => $orders->latest('id'),
        };

        return $this->paginated($orders->paginate($this->perPage()), AdminOrderResource::class);
    }

    public function show(Order $order): JsonResponse
    {
        return $this->success(new AdminOrderResource($this->loadDetail($order)));
    }

    /**
     * Status transitions. Cancelling needs "cancel orders"; everything else "update orders".
     * The transition itself is validated by OrderService (409 when not allowed).
     */
    public function updateStatus(OrderStatusRequest $request, Order $order): JsonResponse
    {
        $next = OrderStatus::from($request->validated('status'));
        $required = $next === OrderStatus::Cancelled ? Permissions::CANCEL_ORDERS : Permissions::UPDATE_ORDERS;

        abort_unless($request->user()->can($required), 403);

        $this->orders->transition($order, $next, $request->user(), $request->safe()->only('tracking_number'));

        return $this->success(new AdminOrderResource($this->loadDetail($order)), "Order marked as {$next->label()}.");
    }

    public function updatePaymentStatus(PaymentStatusRequest $request, Order $order, PaymentService $payments): JsonResponse
    {
        $payments->updateStatusManually($order, PaymentStatus::from($request->validated('payment_status')));

        return $this->success(new AdminOrderResource($this->loadDetail($order->refresh())), 'Payment status updated.');
    }

    public function update(OrderDetailsRequest $request, Order $order): JsonResponse
    {
        $this->orders->updateDetails($order, $request->validated());

        return $this->success(new AdminOrderResource($this->loadDetail($order)), 'Order updated.');
    }

    private function loadDetail(Order $order): Order
    {
        return $order->load([
            'user:id,name,email',
            'items.product' => fn ($q) => $q->withTrashed()->with('primaryImage'),
            'payments',
        ]);
    }
}
