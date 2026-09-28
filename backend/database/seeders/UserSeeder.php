<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEVELOPMENT ACCOUNTS ONLY. Never run this seeder in production.
 *
 *   superadmin@example.com / Password123!
 *   admin@example.com      / Password123!
 *   staff@example.com      / Password123!
 *   customer@example.com   / Password123!
 */
class UserSeeder extends Seeder
{
    public const DEV_PASSWORD = 'Password123!';

    public function run(): void
    {
        $accounts = [
            ['Super Admin', 'superadmin@example.com', RoleName::SuperAdmin],
            ['Alex Admin', 'admin@example.com', RoleName::Admin],
            ['Sam Staff', 'staff@example.com', RoleName::Staff],
            ['Casey Customer', 'customer@example.com', RoleName::Customer],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'password' => self::DEV_PASSWORD,
                'email_verified_at' => now(),
                'is_active' => true,
            ])->save();
            $user->syncRoles([$role->value]);
        }

        $customer = User::query()->where('email', 'customer@example.com')->first();
        if (! $customer->addresses()->exists()) {
            Address::factory()->for($customer)->create([
                'label' => 'Home',
                'first_name' => 'Casey',
                'last_name' => 'Customer',
                'line1' => '221 Market Street',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94105',
                'country' => 'US',
            ]);
        }

        // Additional realistic customers with an address each.
        User::factory()
            ->count(30)
            ->customer()
            ->has(Address::factory(), 'addresses')
            ->create()
            ->each(function (User $user) {
                $user->forceFill(['created_at' => now()->subDays(random_int(1, 120))])->saveQuietly();
            });
    }
}
