<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneGuestCartsTest extends TestCase
{
    public function test_only_stale_guest_carts_are_deleted(): void
    {
        $stale = Cart::create(['token' => (string) Str::uuid()]);
        $stale->forceFill(['updated_at' => now()->subDays(45)])->saveQuietly();

        $fresh = Cart::create(['token' => (string) Str::uuid()]);

        $customerCart = Cart::create(['user_id' => $this->customer()->id]);
        $customerCart->forceFill(['updated_at' => now()->subDays(400)])->saveQuietly();

        $this->artisan('carts:prune-guests', ['--days' => 30])->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($fresh);
        $this->assertModelExists($customerCart);
    }
}
