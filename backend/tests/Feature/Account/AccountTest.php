<?php

namespace Tests\Feature\Account;

use App\Models\Address;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_profile_can_be_viewed_and_updated(): void
    {
        Notification::fake();
        $customer = $this->customer();

        $this->signIn($customer)->getJson('/api/v1/account/profile')->assertOk()->assertJsonPath('data.stats.orders', 0);

        $this->signIn($customer)->putJson('/api/v1/account/profile', ['name' => 'New Name', 'email' => 'new@example.com', 'phone' => '+1 555 0100'])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'New Name')
            // Changing email requires re-verification.
            ->assertJsonPath('data.user.email_verified', false);
    }

    public function test_password_change_requires_current_password(): void
    {
        $customer = $this->customer();

        $this->signIn($customer)->putJson('/api/v1/account/password', [
            'current_password' => 'wrong',
            'password' => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->signIn($customer)->putJson('/api/v1/account/password', [
            'current_password' => 'Password123!',
            'password' => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassw0rd!', $customer->fresh()->password));
    }

    public function test_address_crud_and_default_handling(): void
    {
        $customer = $this->customer();
        $payload = ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'line1' => '1 Main St', 'city' => 'London', 'postal_code' => 'N1 9GU', 'country' => 'gb'];

        $first = $this->signIn($customer)->postJson('/api/v1/account/addresses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.country', 'GB')
            ->assertJsonPath('data.is_default_shipping', true) // first address becomes default
            ->json('data.id');

        $second = $this->signIn($customer)->postJson('/api/v1/account/addresses', $payload + ['is_default_shipping' => true])->json('data.id');

        $this->assertFalse(Address::find($first)->is_default_shipping);
        $this->assertTrue(Address::find($second)->is_default_shipping);

        $this->signIn($customer)->putJson("/api/v1/account/addresses/{$first}", array_merge($payload, ['city' => 'Paris']))->assertOk()->assertJsonPath('data.city', 'Paris');
        $this->signIn($customer)->deleteJson("/api/v1/account/addresses/{$second}")->assertOk();

        // Default is handed to the remaining address.
        $this->assertTrue(Address::find($first)->is_default_shipping);
        $this->signIn($customer)->postJson('/api/v1/account/addresses', ['first_name' => ''])->assertStatus(422);
    }

    public function test_customers_cannot_access_other_customers_addresses(): void
    {
        $address = $this->addressFor($this->customer());
        $intruder = $this->customer();

        $this->signIn($intruder)->getJson("/api/v1/account/addresses/{$address->id}")->assertForbidden();
        $this->signIn($intruder)->putJson("/api/v1/account/addresses/{$address->id}", ['first_name' => 'X', 'last_name' => 'Y', 'line1' => 'Z', 'city' => 'C', 'postal_code' => '12345', 'country' => 'US'])->assertForbidden();
        $this->signIn($intruder)->deleteJson("/api/v1/account/addresses/{$address->id}")->assertForbidden();
        $this->assertModelExists($address);
    }

    public function test_wishlist_add_remove_no_duplicates_and_move_to_cart(): void
    {
        $customer = $this->customer();
        $product = $this->product(1000, 5);

        $this->signIn($customer)->postJson('/api/v1/account/wishlist', ['product_id' => $product->id])->assertCreated();
        $this->signIn($customer)->postJson('/api/v1/account/wishlist', ['product_id' => $product->id])->assertCreated();

        $this->signIn($customer)->getJson('/api/v1/account/wishlist')->assertOk()->assertJsonCount(1, 'data');
        $this->signIn($customer)->getJson('/api/v1/account/wishlist/ids')->assertJsonPath('data', [$product->id]);

        $this->signIn($customer)->postJson("/api/v1/account/wishlist/{$product->id}/move-to-cart")->assertOk();
        $this->signIn($customer)->getJson('/api/v1/account/wishlist')->assertJsonCount(0, 'data');
        $this->signIn($customer)->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 1);

        $this->signIn($customer)->postJson('/api/v1/account/wishlist', ['product_id' => $product->id]);
        $this->signIn($customer)->deleteJson("/api/v1/account/wishlist/{$product->id}")->assertOk();
        $this->signIn($customer)->getJson('/api/v1/account/wishlist')->assertJsonCount(0, 'data');
    }

    public function test_wishlist_requires_authentication(): void
    {
        $this->postJson('/api/v1/account/wishlist', ['product_id' => $this->product()->id])->assertUnauthorized();
    }
}
