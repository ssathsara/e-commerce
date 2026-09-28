<?php

namespace App\Services\Account;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the invariant: a customer has at most one default shipping and one
 * default billing address, and their first address becomes the default for both.
 */
class AddressService
{
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $isFirst = ! $user->addresses()->exists();

            $address = $user->addresses()->make($data);
            $address->is_default_shipping = $isFirst || ($data['is_default_shipping'] ?? false);
            $address->is_default_billing = $isFirst || ($data['is_default_billing'] ?? false);
            $address->save();

            $this->clearOtherDefaults($address);

            return $address;
        });
    }

    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            $address->fill($data)->save();
            $this->clearOtherDefaults($address);

            return $address;
        });
    }

    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $wasShipping = $address->is_default_shipping;
            $wasBilling = $address->is_default_billing;

            $address->delete();

            // Promote the most recent remaining address to keep a default available.
            $next = Address::query()->where('user_id', $address->user_id)->latest('id')->first();
            if ($next !== null && ($wasShipping || $wasBilling)) {
                $next->is_default_shipping = $next->is_default_shipping || $wasShipping;
                $next->is_default_billing = $next->is_default_billing || $wasBilling;
                $next->save();
            }
        });
    }

    private function clearOtherDefaults(Address $address): void
    {
        $others = Address::query()->where('user_id', $address->user_id)->whereKeyNot($address->id);

        if ($address->is_default_shipping) {
            (clone $others)->update(['is_default_shipping' => false]);
        }

        if ($address->is_default_billing) {
            (clone $others)->update(['is_default_billing' => false]);
        }
    }
}
