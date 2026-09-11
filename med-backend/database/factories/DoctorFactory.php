<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'user_id'          => User::factory()->doctor(),
            'specialization'   => fake()->randomElement([
                'Cardiologia', 'Dermatologia', 'Ortopedia', 'Medicina generale',
            ]),
            'license_number'   => fake()->unique()->numerify('MED######'),
            'consultation_fee' => fake()->randomElement([50, 80, 100, 120]),
            'slot_duration'    => 30,
            'available_online' => true,
        ];
    }

    /** Profilo senza tariffa: usato per verificare che non nascano fatture da 0. */
    public function senzaTariffa(): static
    {
        return $this->state(fn () => ['consultation_fee' => 0]);
    }
}
