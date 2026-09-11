<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'codice_fiscale' => strtoupper(fake()->unique()->bothify('??????##?##?###?')),
            'birth_date'     => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'gender'         => fake()->randomElement(['M', 'F']),
            'city'           => fake('it_IT')->city(),
            'province'       => strtoupper(fake()->lexify('??')),
        ];
    }
}
