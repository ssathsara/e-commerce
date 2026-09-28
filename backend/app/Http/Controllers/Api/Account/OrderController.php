<?php

namespace App\Http\Controllers\Api\Account;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PayOrderRequest;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Customer order endpoints: checkout, history, detail, cancellation and payment retry.
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'string', 'max:20']]);

        $orders = $request->user()->orders()
            ->withCount('items')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perPage(10));

        return $this->paginated($orders, OrderResource::class);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return $this->success(new OrderResource($this->loadDetail($order)));
    }

    /**
     * Places an order from the customer's cart. Idempotent on "idempotency_key":
     * a repeated request returns the original order with 200 instead of creating another.
     */
    public function store(PlaceOrderRequest $request, CheckoutService $checkout): JsonResponse
    {
        $result = $checkout->placeOrder($request->user(), $request->toData());
        $order = $this->loadDetail($result->order);

        if (! $result->created) {
            return $this->success(new OrderResource($order), 'This order was already placed.');
        }

        $message = $result->paymentError !== null
            ? "Your order was created but the payment failed: {$result->paymentError} You can retry the payment from your order page."
            : 'Thank you! Your order has been placed.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => (new OrderResource($order))->resolve($request),
            'payment_error' => $result->paymentError,
        ], 201);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        Gate::authorize('cancel', $order);

        $orders->cancelByCustomer($order, $request->user());

        return $this->success(new OrderResource($this->loadDetail($order)), 'Your order has been cancelled.');
    }

    /** Retry an online payment for an unpaid order (idempotent on idempotency_key). */
    public function pay(PayOrderRequest $request, Order $order, PaymentService $payments): JsonResponse
    {
        Gate::authorize('pay', $order);

        $payment = $payments->charge($order, $request->validated('payment_token'), $request->validated('idempotency_key'));
        $order = $this->loadDetail($order->refresh());

        if ($payment->status === PaymentStatus::Failed) {
            return response()->json([
                'success' => false,
                'message' => $payment->failure_reason ?? 'The payment could not be completed.',
                'data' => (new OrderResource($order))->resolve($request),
            ], 402);
        }

        return $this->success(new OrderResource($order), 'Payment received. Thank you!');
    }

    private function loadDetail(Order $order): Order
    {
        return $order->load([
            'items.product' => fn ($q) => $q->withTrashed()->with('primaryImage'),
            'payments',
        ]);
    }
}
