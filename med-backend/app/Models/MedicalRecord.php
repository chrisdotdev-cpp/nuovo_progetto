<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Voce della cartella clinica. */
class MedicalRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'type',
        'title',
        'description',
        'vitals',
        'icd10_code',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'vitals'      => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        return blank($type) ? $query : $query->where('type', $type);
    }

    /**
     * Finestra di rettifica: una voce clinica resta modificabile solo 48h.
     * Dopo diventa immutabile (si crea una nuova voce di rettifica).
     */
    public function isEditable(): bool
    {
        return $this->created_at?->diffInHours(now()) < 48;
    }
}
