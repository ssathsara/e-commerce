<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;

class SendWelcomeNotification
{
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $event->user->notify(new WelcomeNotification);
        }
    }
}
