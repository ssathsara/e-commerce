<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderStatusChangedNotification;

class SendOrderStatusNotification
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing('user');

        $order->user?->notify(new OrderStatusChangedNotification($order, $event->previous));
    }
}
