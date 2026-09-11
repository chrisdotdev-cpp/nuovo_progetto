<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\TelemedicineSession;
use Illuminate\Support\Str;

/**
 * Gestione delle stanze di teleconsulto.
 * Il provider video (Jitsi, Daily, Twilio) si integra qui: il resto
 * dell'applicazione conosce solo room_code e stato.
 */
class TelemedicineService
{
    public function createSessionFor(Appointment $appointment): TelemedicineSession
    {
        return TelemedicineSession::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'room_code' => strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4)),
                'status'    => 'programmata',
            ]
        );
    }

    public function start(TelemedicineSession $session): TelemedicineSession
    {
        $session->update([
            'status'     => 'in_corso',
            'started_at' => $session->started_at ?? now(),
        ]);

        return $session->fresh();
    }

    public function end(TelemedicineSession $session, ?string $notes = null): TelemedicineSession
    {
        $endedAt = now();

        $session->update([
            'status'           => 'terminata',
            'ended_at'         => $endedAt,
            'duration_seconds' => $session->started_at ? $session->started_at->diffInSeconds($endedAt) : null,
            'notes'            => $notes ?? $session->notes,
        ]);

        // Chiusa la sessione, l'appuntamento risulta completato
        $session->appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        return $session->fresh();
    }
}
