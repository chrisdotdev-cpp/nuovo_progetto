<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fascia oraria settimanale del medico.
 *
 * Senza almeno una fascia attiva AppointmentService::assertSlotIsFree() rifiuta
 * qualsiasi prenotazione ("fuori dall'agenda del medico"): questa factory e'
 * quindi il presupposto di quasi tutti i test sulle prenotazioni.
 *
 * @extends Factory<DoctorSchedule>
 */
class DoctorScheduleFactory extends Factory
{
    protected $model = DoctorSchedule::class;

    public function definition(): array
    {
        return [
            'doctor_id'  => Doctor::factory(),
            'weekday'    => 1,            // ISO-8601: 1 = lunedi
            'start_time' => '08:00:00',
            'end_time'   => '20:00:00',
            'active'     => true,
        ];
    }

    /** Giorno della settimana in formato ISO (1 = lunedi ... 7 = domenica). */
    public function weekday(int $weekday): static
    {
        return $this->state(fn () => ['weekday' => $weekday]);
    }

    /** Fascia oraria personalizzata, es. fascia('09:00:00', '13:00:00'). */
    public function fascia(string $start, string $end): static
    {
        return $this->state(fn () => ['start_time' => $start, 'end_time' => $end]);
    }

    /** Fascia sospesa: esiste a calendario ma non genera slot prenotabili. */
    public function inattiva(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
