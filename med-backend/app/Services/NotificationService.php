<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppNotification;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;

/**
 * Punto unico di creazione delle notifiche in-app.
 * Centralizzarle qui evita testi duplicati e link incoerenti tra i moduli.
 */
class NotificationService
{
    public function push(
        int $userId,
        string $title,
        ?string $body = null,
        string $category = 'sistema',
        string $level = 'info',
        ?string $link = null
    ): AppNotification {
        return AppNotification::create([
            'user_id'  => $userId,
            'title'    => $title,
            'body'     => $body,
            'category' => $category,
            'level'    => $level,
            'link'     => $link,
        ]);
    }

    public function appointmentCreated(Appointment $appointment): void
    {
        $appointment->loadMissing(['patient.user', 'doctor.user']);
        $quando = $appointment->scheduled_at->format('d/m/Y H:i');

        // Al paziente
        $this->push(
            $appointment->patient->user_id,
            'Appuntamento prenotato',
            "Visita con {$appointment->doctor->user->name} il {$quando}.",
            'appuntamento',
            'successo',
            '/paziente/appuntamenti'
        );

        // Al medico
        $this->push(
            $appointment->doctor->user_id,
            'Nuovo appuntamento in agenda',
            "{$appointment->patient->user->name} - {$quando}.",
            'appuntamento',
            'info',
            '/medico/agenda'
        );
    }

    public function appointmentRescheduled(Appointment $appointment): void
    {
        $appointment->loadMissing(['patient.user', 'doctor.user']);
        $quando = $appointment->scheduled_at->format('d/m/Y H:i');

        $this->push(
            $appointment->patient->user_id,
            'Appuntamento spostato',
            "La visita e' stata spostata al {$quando}.",
            'appuntamento',
            'attenzione',
            '/paziente/appuntamenti'
        );
    }

    public function appointmentCancelled(Appointment $appointment): void
    {
        $appointment->loadMissing(['patient.user', 'doctor.user']);
        $quando = $appointment->scheduled_at->format('d/m/Y H:i');

        foreach ([$appointment->patient->user_id, $appointment->doctor->user_id] as $userId) {
            $this->push(
                $userId,
                'Appuntamento annullato',
                "L'appuntamento del {$quando} e' stato annullato.",
                'appuntamento',
                'errore'
            );
        }
    }

    public function prescriptionIssued(Prescription $prescription): void
    {
        $prescription->loadMissing(['patient', 'doctor.user']);

        $this->push(
            $prescription->patient->user_id,
            'Nuova prescrizione disponibile',
            "Il dott. {$prescription->doctor->user->name} ha emesso la ricetta {$prescription->code}.",
            'prescrizione',
            'successo',
            '/paziente/cartella-clinica'
        );
    }

    /* ---------------------------------------------------------------------
     | Ciclo di fatturazione
     * -------------------------------------------------------------------*/

    /** Fattura emessa: il paziente trova l'importo da saldare in "Pagamenti". */
    public function invoiceIssued(Invoice $invoice): void
    {
        $invoice->loadMissing('patient');

        if (! $invoice->patient?->user_id) {
            return;
        }

        $importo   = number_format((float) $invoice->total, 2, ',', '.');
        $scadenza  = $invoice->due_date?->format('d/m/Y');
        $dettaglio = $scadenza
            ? "Fattura {$invoice->number} di € {$importo}, da saldare entro il {$scadenza}."
            : "Fattura {$invoice->number} di € {$importo}.";

        $this->push(
            $invoice->patient->user_id,
            'Nuova fattura da saldare',
            $dettaglio,
            'pagamento',
            'attenzione',
            '/paziente/pagamenti'
        );
    }

    /**
     * Incasso registrato.
     * Al paziente serve la ricevuta, all'amministrazione l'aggiornamento di cassa:
     * e' l'evento che fa ricalcolare i KPI del Finanziario.
     */
    public function paymentRegistered(Invoice $invoice, Payment $payment): void
    {
        $invoice->loadMissing('patient.user');

        $importo = number_format((float) $payment->amount, 2, ',', '.');
        $saldata = $invoice->status === 'pagata';

        // Conferma al paziente
        if ($invoice->patient?->user_id) {
            $this->push(
                $invoice->patient->user_id,
                $saldata ? 'Fattura saldata' : 'Acconto registrato',
                $saldata
                    ? "Ricevuto € {$importo}: la fattura {$invoice->number} risulta pagata."
                    : "Ricevuto € {$importo} sulla fattura {$invoice->number}. Residuo € "
                      . number_format($invoice->balance, 2, ',', '.') . '.',
                'pagamento',
                'successo',
                '/paziente/pagamenti'
            );
        }

        // Avviso all'amministrazione
        $paziente = $invoice->patient?->user?->name ?? 'Paziente';

        User::role(User::ROLE_ADMIN)->active()->pluck('id')->each(
            fn (int $adminId) => $this->push(
                $adminId,
                'Pagamento ricevuto',
                "{$paziente} · fattura {$invoice->number} · € {$importo} ({$payment->method}).",
                'pagamento',
                'successo',
                '/admin/finanziario'
            )
        );
    }

    public function documentToSign(Document $document): void
    {
        // Tutti gli amministratori vengono avvisati dei documenti in attesa di firma
        User::role(User::ROLE_ADMIN)->active()->pluck('id')->each(function (int $adminId) use ($document) {
            $this->push(
                $adminId,
                'Documento da firmare',
                $document->title,
                'sistema',
                'attenzione',
                '/admin/documenti'
            );
        });
    }
}
