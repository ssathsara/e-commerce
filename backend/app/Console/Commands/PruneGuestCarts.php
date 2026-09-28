<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;

/**
 * Guest carts are created for anonymous visitors and would otherwise accumulate
 * forever. Scheduled daily in routes/console.php.
 */
class PruneGuestCarts extends Command
{
    protected $signature = 'carts:prune-guests {--days=30 : Delete guest carts not updated for this many days}';

    protected $description = 'Delete abandoned guest carts';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = Cart::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} guest cart(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
