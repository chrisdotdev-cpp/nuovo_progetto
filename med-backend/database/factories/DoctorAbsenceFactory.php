<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Periodo di indisponibilita' del medico (ferie, congressi, chiusure).
 *
 * @extends Factory<DoctorAbsence>
 */
class DoctorAbsenceFactory extends Factory
{
    protected $model = DoctorAbsence::class;

    public function definition(): array
    {
        return [
            'doctor_id'  => Doctor::factory(),
            'start_date' => now()->addDay()->toDateString(),
            'end_date'   => now()->addDays(7)->toDateString(),
            'reason'     => 'Ferie',
        ];
    }

    /** Assenza che copre una data precisa: comodo per verificare il blocco di uno slot. */
    public function nelGiorno(string $data): static
    {
        return $this->state(fn () => ['start_date' => $data, 'end_date' => $data]);
    }
}
