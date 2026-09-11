<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\InvoiceService;

/**
 * Aggancio fra agenda e contabilita'.
 *
 * La fattura nasce quando la prestazione e' erogata, cioe' quando lo stato
 * dell'appuntamento diventa "completato". Sta in un Observer e non nel
 * controller perche' il passaggio di stato avviene da piu' punti (agenda del
 * medico, pannello admin, futuri job di chiusura automatica) e la regola
 * contabile deve valere per tutti allo stesso modo.
 */
class AppointmentObserver
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function updated(Appointment $appointment): void
    {
        // isDirty() dentro updated() legge ancora il delta del salvataggio appena fatto
        if (! $appointment->wasChanged('status')) {
            return;
        }

        if (! $this->daFatturare($appointment->status)) {
            return;
        }

        // Non blocca mai la chiusura della visita: il service logga e prosegue
        $this->invoices->createFromAppointment($appointment);
    }

    /**
     * Stati che rendono la prestazione esigibile.
     * Il no-show e' opzionale: alcune strutture addebitano una penale, altre no.
     */
    private function daFatturare(string $status): bool
    {
        if ($status === Appointment::STATUS_COMPLETATO) {
            return true;
        }

        return $status === Appointment::STATUS_ASSENTE
            && (bool) config('billing.invoice_no_show', false);
    }
}
