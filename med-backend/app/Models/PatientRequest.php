<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Richiesta asincrona del paziente al medico (triage). */
class PatientRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'subject',
        'description',
        'priority',
        'status',
        'response',
        'responded_at',
        'appointment_id',
        'prescription_id',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
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

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class);
    }

    public function scopePriority(Builder $query, ?string $priority): Builder
    {
        return blank($priority) ? $query : $query->where('priority', $priority);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    /**
     * Ordinamento di triage: prima le alte priorita', poi le piu' vecchie.
     * FIELD() funziona su MySQL; su altri driver si puo' sostituire con un CASE.
     */
    public function scopeTriageOrder(Builder $query): Builder
    {
        /*
         * FIELD() esiste solo in MySQL: sotto SQLite (suite di test, eventuali
         * ambienti leggeri) la query moriva con "no such function: FIELD",
         * portandosi dietro l'intera dashboard del medico. Il CASE esplicito
         * produce lo stesso ordinamento ed e' SQL standard.
         */
        return $query->orderByRaw("CASE priority WHEN 'alta' THEN 1 WHEN 'media' THEN 2 WHEN 'bassa' THEN 3 ELSE 4 END")
                     ->orderBy('created_at');
    }
}
