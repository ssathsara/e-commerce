<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Run the scheduler every minute in production:  * * * * * php artisan schedule:run
*/

Schedule::command('carts:prune-guests --days=30')->daily()->withoutOverlapping();

// Expired password-reset tokens.
Schedule::command('auth:clear-resets')->daily();

// Remove Sanctum personal access tokens that expired more than a day ago (if any are issued).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
