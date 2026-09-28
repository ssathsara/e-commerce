<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Notifications\PaymentConfirmedNotification;

class SendPaymentConfirmation
{
    public function handle(PaymentSucceeded $event): void
    {
        $order = $event->order->loadMissing('user');

        $order->user?->notify(new PaymentConfirmedNotification($order));
    }
}
