<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on every customer-visible order status change, including shipping updates.
 */
class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $previous,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $status = $order->status;

        $mail = (new MailMessage)
            ->subject("Order {$order->order_number}: {$status->label()}")
            ->greeting("Hi {$notifiable->name},");

        match ($status) {
            OrderStatus::Shipped => $mail->line("Good news — order {$order->order_number} has shipped.")
                ->line($order->tracking_number ? "Tracking number: {$order->tracking_number}" : 'Tracking information will follow.'),
            OrderStatus::Delivered => $mail->line("Order {$order->order_number} has been delivered. We hope you enjoy it!")
                ->line('You can now review the products you purchased.'),
            OrderStatus::Cancelled => $mail->line("Order {$order->order_number} has been cancelled."),
            OrderStatus::Refunded => $mail->line("Order {$order->order_number} has been refunded."),
            default => $mail->line("The status of order {$order->order_number} changed from {$this->previous->label()} to {$status->label()}."),
        };

        return $mail->action('View order', config('shop.frontend_url')."/account/orders/{$order->id}");
    }
}
