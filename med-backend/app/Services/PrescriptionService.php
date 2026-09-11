<?php

namespace App\Services;

use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

/**
 * Emissione ricette: numerazione progressiva annua + righe farmaco.
 */
class PrescriptionService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function create(array $data, int $doctorId): Prescription
    {
        return DB::transaction(function () use ($data, $doctorId) {
            $prescription = Prescription::create([
                'code'           => $this->nextCode(),
                'patient_id'     => $data['patient_id'],
                'doctor_id'      => $doctorId,
                'appointment_id' => $data['appointment_id'] ?? null,
                'status'         => 'attiva',
                'notes'          => $data['notes'] ?? null,
                'issued_at'      => $data['issued_at'] ?? now()->toDateString(),
                'valid_until'    => $data['valid_until'] ?? now()->addMonths(6)->toDateString(),
            ]);

            $prescription->items()->createMany($data['items']);

            $this->notifications->prescriptionIssued($prescription);

            return $prescription->load(['items.medicine', 'patient.user', 'doctor.user']);
        });
    }

    public function update(Prescription $prescription, array $data): Prescription
    {
        return DB::transaction(function () use ($prescription, $data) {
            $prescription->update(collect($data)->except('items')->toArray());

            // Le righe si sostituiscono in blocco: piu' semplice e coerente di un diff parziale
            if (isset($data['items'])) {
                $prescription->items()->delete();
                $prescription->items()->createMany($data['items']);
            }

            return $prescription->fresh(['items.medicine', 'patient.user', 'doctor.user']);
        });
    }

    /**
     * Numerazione ricetta: RX-2026-000123.
     * Il lock sulla tabella evita duplicati in concorrenza.
     */
    private function nextCode(): string
    {
        $year = now()->year;

        $last = Prescription::withTrashed()
            ->where('code', 'like', "RX-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $next = $last ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('RX-%d-%06d', $year, $next);
    }
}
