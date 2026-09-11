<?php

namespace Database\Factories;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Notifica in-app.
 *
 * @extends Factory<AppNotification>
 */
class AppNotificationFactory extends Factory
{
    protected $model = AppNotification::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'category' => 'sistema',
            'level'    => 'info',
            'title'    => fake('it_IT')->sentence(4),
            'body'     => fake('it_IT')->sentence(10),
            'link'     => null,
            'read_at'  => null,
        ];
    }

    public function letta(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }

    public function categoria(string $categoria): static
    {
        return $this->state(fn () => ['category' => $categoria]);
    }
}
