<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'patient_id'       => Patient::factory(),
            'doctor_id'        => Doctor::factory(),
            'scheduled_at'     => now()->addDay()->setTime(10, 0),
            'duration_minutes' => 30,
            'type'             => 'visita',
            'status'           => Appointment::STATUS_CONFERMATO,
            'reason'           => fake('it_IT')->sentence(4),
        ];
    }

    public function completato(): static
    {
        return $this->state(fn () => [
            'scheduled_at' => now()->subHour(),
            'status'       => Appointment::STATUS_COMPLETATO,
        ]);
    }
}
