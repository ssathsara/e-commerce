<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format(Money::toMajor($this->order->grand_total), 2).' '.$this->order->currency;

        return (new MailMessage)
            ->subject("Payment received for order {$this->order->order_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("We have received your payment of {$amount}.")
            ->action('View order', config('shop.frontend_url')."/account/orders/{$this->order->id}");
    }
}
