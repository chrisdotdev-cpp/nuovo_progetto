<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Periodo di indisponibilita' del medico (ferie, congressi, chiusure). */
class DoctorAbsence extends Model
{
    use HasFactory;

    protected $fillable = ['doctor_id', 'start_date', 'end_date', 'reason'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
