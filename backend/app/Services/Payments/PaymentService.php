<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Events\PaymentSucceeded;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentService
{
    /** A pending online payment younger than this blocks new attempts (it may still be in flight). */
    private const IN_FLIGHT_SECONDS = 120;

    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    /**
     * Charge an order online. Idempotent on $idempotencyKey: replaying the same key
     * returns the original payment instead of charging again.
     */
    public function charge(Order $order, string $paymentToken, string $idempotencyKey): Payment
    {
        // Step 1 — reserve a payment attempt under an order lock.
        $payment = DB::transaction(function () use ($order, $idempotencyKey) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->order_id !== $locked->id) {
                    throw BusinessRuleException::conflict('This payment request has already been used.');
                }

                return $existing;
            }

            if ($locked->payment_method !== PaymentMethod::Card) {
                throw BusinessRuleException::unprocessable('This order is not paid by card.', 'payment_method');
            }

            if ($locked->payment_status === PaymentStatus::Paid) {
                throw BusinessRuleException::conflict('This order has already been paid.');
            }

            if (in_array($locked->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
                throw BusinessRuleException::conflict('This order can no longer be paid.');
            }

            $inFlight = $locked->payments()
                ->where('status', PaymentStatus::Pending)
                ->where('created_at', '>=', now()->subSeconds(self::IN_FLIGHT_SECONDS))
                ->exists();

            if ($inFlight) {
                throw BusinessRuleException::conflict('A payment for this order is already being processed.');
            }

            return $locked->payments()->create([
                'gateway' => $this->gateway->name(),
                'method' => PaymentMethod::Card,
                'status' => PaymentStatus::Pending,
                'amount' => $locked->grand_total,
                'currency' => $locked->currency,
                'idempotency_key' => $idempotencyKey,
            ]);
        });

        if (! $payment->wasRecentlyCreated) {
            return $payment;
        }

        // Step 2 — talk to the provider outside of any DB transaction/lock.
        try {
            $result = $this->gateway->charge(new ChargeRequest(
                amount: $payment->amount,
                currency: $payment->currency,
                paymentToken: $paymentToken,
                idempotencyKey: $idempotencyKey,
                description: "Order {$order->order_number}",
                metadata: ['order_id' => $order->id],
            ));
        } catch (Throwable $e) {
            Log::error('Payment gateway error', ['order_id' => $order->id, 'payment_id' => $payment->id, 'exception' => $e->getMessage()]);
            $result = PaymentResult::failure('The payment provider is unavailable. Please try again.');
        }

        // Step 3 — record the outcome.
        return DB::transaction(function () use ($order, $payment, $result) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($result->successful) {
                $payment->fill([
                    'status' => PaymentStatus::Paid,
                    'transaction_reference' => $result->reference,
                    'meta' => $result->meta,
                    'paid_at' => now(),
                ])->save();

                $this->markOrderPaid($locked, $payment);
            } else {
                $payment->fill([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => $result->failureReason,
                    'meta' => $result->meta,
                ])->save();

                if ($locked->payment_status !== PaymentStatus::Paid) {
                    $locked->payment_status = PaymentStatus::Failed;
                    $locked->save();
                }

                AuditLog::warning('payment.failed', ['order_id' => $locked->id, 'payment_id' => $payment->id, 'reason' => $result->failureReason]);
            }

            $order->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        });
    }

    /** Offline methods (cash on delivery) get a pending payment record at checkout. */
    public function recordOfflinePayment(Order $order): Payment
    {
        return $order->payments()->create([
            'gateway' => 'offline',
            'method' => $order->payment_method,
            'status' => PaymentStatus::Pending,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'idempotency_key' => "order-{$order->id}-offline",
        ]);
    }

    /**
     * Refund the captured payment of an order (full refund). Caller must hold the order lock.
     */
    public function refund(Order $order): void
    {
        /** @var Payment|null $payment */
        $payment = $order->payments()->where('status', PaymentStatus::Paid)->latest('id')->first();

        if ($payment === null) {
            return;
        }

        if ($payment->method->isOnline()) {
            $result = $this->gateway->refund($payment);

            if (! $result->successful) {
                throw BusinessRuleException::conflict('The refund could not be processed: '.$result->failureReason);
            }

            $payment->meta = array_merge($payment->meta ?? [], ['refund_reference' => $result->reference]);
        }

        $payment->status = PaymentStatus::Refunded;
        $payment->refunded_at = now();
        $payment->save();

        $order->payment_status = PaymentStatus::Refunded;
        $order->save();

        AuditLog::info('payment.refunded', ['order_id' => $order->id, 'payment_id' => $payment->id, 'amount' => $payment->amount]);
    }

    /**
     * Manual payment status change from the admin portal. Only allowed for offline
     * payment methods: card payment status is owned by the gateway.
     */
    public function updateStatusManually(Order $order, PaymentStatus $next): Order
    {
        return DB::transaction(function () use ($order, $next) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_method->isOnline()) {
                throw BusinessRuleException::conflict('Card payment status is managed by the payment provider and cannot be changed manually.');
            }

            if (! $locked->payment_status->canTransitionTo($next)) {
                throw BusinessRuleException::conflict("Payment status cannot change from {$locked->payment_status->label()} to {$next->label()}.");
            }

            if ($next === PaymentStatus::Refunded) {
                $this->refund($locked);

                return $locked;
            }

            /** @var Payment $payment */
            $payment = $locked->payments()->latest('id')->first() ?? $this->recordOfflinePayment($locked);
            $payment->status = $next;
            $payment->paid_at = $next === PaymentStatus::Paid ? now() : null;
            $payment->save();

            if ($next === PaymentStatus::Paid) {
                $this->markOrderPaid($locked, $payment);
            } else {
                $locked->payment_status = $next;
                $locked->save();
            }

            AuditLog::info('payment.status_updated', ['order_id' => $locked->id, 'status' => $next->value]);

            return $locked;
        });
    }

    /** Marks the order paid and auto-confirms it if still pending. */
    private function markOrderPaid(Order $order, Payment $payment): void
    {
        $order->payment_status = PaymentStatus::Paid;

        $previous = $order->status;
        if ($order->status === OrderStatus::Pending) {
            $order->status = OrderStatus::Confirmed;
            $order->confirmed_at = now();
        }

        $order->save();

        AuditLog::info('payment.succeeded', ['order_id' => $order->id, 'payment_id' => $payment->id, 'amount' => $payment->amount]);

        event(new PaymentSucceeded($order, $payment));

        if ($previous !== $order->status) {
            event(new OrderStatusChanged($order, $previous));
        }
    }
}
