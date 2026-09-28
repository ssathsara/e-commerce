<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.6)->numerify('+1 (###) ###-####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123!'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function customer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(RoleName::Customer->value));
    }

    public function withRole(RoleName|string $role): static
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        return $this->afterCreating(fn (User $user) => $user->assignRole($name));
    }
}
