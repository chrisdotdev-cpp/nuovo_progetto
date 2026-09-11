<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sessione di teleconsulto. */
class TelemedicineSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'room_code',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'ended_at'         => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelemedicineMessage::class)->oldest();
    }

    /** La stanza si apre 10 minuti prima e resta valida per tutta la durata. */
    public function isJoinable(): bool
    {
        $appointment = $this->appointment;

        if (! $appointment || $this->status === 'terminata') {
            return false;
        }

        return now()->between(
            $appointment->scheduled_at->copy()->subMinutes(10),
            $appointment->scheduled_at->copy()->addMinutes($appointment->duration_minutes + 30)
        );
    }
}
