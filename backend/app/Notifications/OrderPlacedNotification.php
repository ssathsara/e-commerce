<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items');

        $mail = (new MailMessage)
            ->subject("Order {$order->order_number} received")
            ->greeting("Thank you for your order, {$notifiable->name}!")
            ->line("We have received order {$order->order_number}.");

        foreach ($order->items as $item) {
            $mail->line(sprintf(
                '%d x %s%s — %s %s',
                $item->quantity,
                $item->product_name,
                $item->variant_name ? " ({$item->variant_name})" : '',
                number_format(Money::toMajor($item->line_total), 2),
                $order->currency,
            ));
        }

        return $mail
            ->line('Total: '.number_format(Money::toMajor($order->grand_total), 2).' '.$order->currency)
            ->action('View order', config('shop.frontend_url')."/account/orders/{$order->id}");
    }
}
