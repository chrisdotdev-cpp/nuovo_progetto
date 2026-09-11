<?php

namespace App\Models;

use App\Observers\AppointmentObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Appuntamento/prenotazione.
 *
 * L'observer chiude il ciclo verso la contabilita': alla transizione in
 * "completato" emette la fattura al paziente.
 */
#[ObservedBy(AppointmentObserver::class)]
class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_IN_ATTESA  = 'in_attesa';
    public const STATUS_CONFERMATO = 'confermato';
    public const STATUS_COMPLETATO = 'completato';
    public const STATUS_ANNULLATO  = 'annullato';
    public const STATUS_ASSENTE    = 'assente';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'scheduled_at',
        'duration_minutes',
        'type',
        'status',
        'reason',
        'notes',
        'cancellation_reason',
        'cancelled_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'     => 'datetime',
            'confirmed_at'     => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    protected $appends = ['ends_at'];

    /** Orario di fine calcolato: serve al calendario Vue e ai controlli di sovrapposizione. */
    public function getEndsAtAttribute(): ?string
    {
        return $this->scheduled_at?->copy()->addMinutes($this->duration_minutes ?? 30)->toIso8601String();
    }

    /* ---------------------------------------------------------------------
     | Relazioni
     * -------------------------------------------------------------------*/

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function telemedicineSession(): HasOne
    {
        return $this->hasOne(TelemedicineSession::class);
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /* ---------------------------------------------------------------------
     | Scopes
     * -------------------------------------------------------------------*/

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now())
                     ->whereNotIn('status', [self::STATUS_ANNULLATO])
                     ->orderBy('scheduled_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('scheduled_at', '<', now())->orderByDesc('scheduled_at');
    }

    /**
     * Appuntamenti compresi fra due estremi.
     *
     * ATTENZIONE ai bound espressi come sola data.
     * Agenda del medico e Prenotazioni dell'admin filtrano un singolo giorno
     * passando from = to = '2026-08-05'. MySQL promuove entrambe le stringhe a
     * '2026-08-05 00:00:00', quindi
     *
     *   WHERE scheduled_at BETWEEN '2026-08-05 00:00:00' AND '2026-08-05 00:00:00'
     *
     * intercettava SOLO gli appuntamenti fissati esattamente a mezzanotte: in
     * pratica nessuno. Era il motivo per cui l'agenda del medico restava vuota
     * anche dopo una prenotazione andata a buon fine.
     *
     * Qui un estremo senza orario viene esteso all'intera giornata, mentre un
     * datetime completo resta rispettato al secondo.
     */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        $inizio = CarbonImmutable::parse($from);
        $fine   = CarbonImmutable::parse($to);

        // La presenza di ":" distingue '2026-08-05' da '2026-08-05 14:30:00'
        if (! str_contains($from, ':')) {
            $inizio = $inizio->startOfDay();
        }

        if (! str_contains($to, ':')) {
            $fine = $fine->endOfDay();
        }

        return $query->whereBetween('scheduled_at', [$inizio, $fine]);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    /** Un appuntamento e' modificabile finche' non e' concluso o annullato. */
    public function isEditable(): bool
    {
        return ! in_array($this->status, [self::STATUS_COMPLETATO, self::STATUS_ANNULLATO], true);
    }
}
