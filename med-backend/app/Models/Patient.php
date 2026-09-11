<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Anagrafica clinica del paziente.
 */
class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'codice_fiscale',
        'birth_date',
        'gender',
        'birth_place',
        'address',
        'city',
        'province',
        'postal_code',
        'blood_type',
        'allergies',
        'chronic_conditions',
        'notes',
        'emergency_contact_name',
        'emergency_contact_phone',
        'primary_doctor_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'         => 'date',
            'allergies'          => 'array',
            'chronic_conditions' => 'array',
        ];
    }

    // Eta' calcolata, comoda per il frontend senza logica duplicata in Vue
    protected $appends = ['age'];

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    /* ---------------------------------------------------------------------
     | Relazioni
     * -------------------------------------------------------------------*/

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'primary_doctor_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class)->latest('recorded_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PatientRequest::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /* ---------------------------------------------------------------------
     | Scopes
     * -------------------------------------------------------------------*/

    /** Ricerca su nome/email dell'account e sul codice fiscale. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('codice_fiscale', 'like', "%{$term}%")
              ->orWhereHas('user', fn (Builder $u) => $u->search($term));
        });
    }

    /**
     * Pazienti seguiti da un medico: medico di base oppure con appuntamenti con lui.
     *
     * L'argomento e' nullable di proposito. PatientController passa
     * `$user->doctor?->id`, che vale null quando l'account ha ruolo 'medico' ma
     * non ha ancora il record in `doctors`. Con la firma `int` PHP non converte
     * null in 0: solleva un TypeError e l'elenco pazienti rispondeva 500 invece
     * di restituire una lista vuota, che e' il comportamento corretto per un
     * profilo non ancora configurato.
     */
    public function scopeOfDoctor(Builder $query, ?int $doctorId): Builder
    {
        if ($doctorId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($doctorId) {
            $q->where('primary_doctor_id', $doctorId)
              ->orWhereHas('appointments', fn (Builder $a) => $a->where('doctor_id', $doctorId));
        });
    }
}
