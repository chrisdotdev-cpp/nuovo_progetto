<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Profilo professionale del medico.
 */
class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'specialization',
        'license_number',
        'bio',
        'consultation_fee',
        'slot_duration',
        'available_online',
    ];

    protected function casts(): array
    {
        return [
            'consultation_fee' => 'decimal:2',
            'slot_duration'    => 'integer',
            'available_online' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(DoctorAbsence::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PatientRequest::class);
    }

    /** Pazienti che hanno questo medico come medico di base. */
    public function assignedPatients(): HasMany
    {
        return $this->hasMany(Patient::class, 'primary_doctor_id');
    }

    public function scopeSpecialization(Builder $query, ?string $value): Builder
    {
        return blank($value) ? $query : $query->where('specialization', $value);
    }

    public function scopeOnlineEnabled(Builder $query): Builder
    {
        return $query->where('available_online', true);
    }
}
