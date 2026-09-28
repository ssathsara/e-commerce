<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderPlacedNotification;

class SendOrderPlacedNotification
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('user');

        $order->user?->notify(new OrderPlacedNotification($order));
    }
}
