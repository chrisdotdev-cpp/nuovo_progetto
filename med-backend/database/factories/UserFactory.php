<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name'              => fake('it_IT')->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => User::ROLE_PAZIENTE,
            'status'            => 'attivo',
            'phone'             => fake('it_IT')->phoneNumber(),
            'remember_token'    => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function doctor(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_MEDICO]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'sospeso']);
    }
}
