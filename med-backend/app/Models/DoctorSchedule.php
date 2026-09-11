<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fascia oraria settimanale ricorrente del medico. */
class DoctorSchedule extends Model
{
    use HasFactory;

    protected $fillable = ['doctor_id', 'weekday', 'start_time', 'end_time', 'active'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'active'  => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
