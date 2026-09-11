<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fatturazione: numerazione, totali e riconciliazione degli incassi.
 * Nessun calcolo di importi deve essere fatto nel controller o in Vue.
 */
class InvoiceService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $items = collect($data['items'])->map(fn (array $item) => [
                'description' => $item['description'],
                'quantity'    => $item['quantity'] ?? 1,
                'unit_price'  => $item['unit_price'],
                'total'       => round(($item['quantity'] ?? 1) * $item['unit_price'], 2),
            ]);

            $subtotal  = round($items->sum('total'), 2);
            $taxRate   = (float) ($data['tax_rate'] ?? 0);
            $taxAmount = round($subtotal * $taxRate / 100, 2);

            $invoice = Invoice::create([
                'number'         => $this->nextNumber(),
                'patient_id'     => $data['patient_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                'issue_date'     => $data['issue_date'] ?? now()->toDateString(),
                'due_date'       => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'subtotal'       => $subtotal,
                'tax_rate'       => $taxRate,
                'tax_amount'     => $taxAmount,
                'total'          => round($subtotal + $taxAmount, 2),
                'status'         => $data['status'] ?? 'emessa',
                'notes'          => $data['notes'] ?? null,
            ]);

            $invoice->items()->createMany($items->toArray());

            $invoice->load(['items', 'patient.user']);

            // Una bozza non e' ancora un documento: il paziente non deve vederla
            if ($invoice->status !== 'bozza') {
                $this->notifications->invoiceIssued($invoice);
            }

            return $invoice;
        });
    }

    /**
     * Fattura generata alla chiusura di un appuntamento.
     *
     * Chiamata dall'AppointmentObserver quando lo stato passa a "completato":
     * e' il momento in cui la prestazione e' effettivamente erogata e quindi
     * diventa esigibile. Il metodo e' volutamente idempotente e silenzioso,
     * perche' un problema di fatturazione non deve mai far fallire la chiusura
     * della visita da parte del medico.
     *
     * @return Invoice|null null quando non c'e' nulla da fatturare
     */
    public function createFromAppointment(Appointment $appointment): ?Invoice
    {
        if (! config('billing.auto_invoice', true)) {
            return null;
        }

        // Idempotenza: relazione hasOne, una sola fattura per appuntamento.
        // Protegge da doppi salvataggi e da un "completato" impostato due volte.
        if ($appointment->invoice()->withTrashed()->exists()) {
            return null;
        }

        $appointment->loadMissing(['doctor', 'patient']);

        $importo = (float) ($appointment->doctor?->consultation_fee ?: 0);

        if ($importo <= 0) {
            $importo = (float) config('billing.fallback_fee', 0);
        }

        // Tariffa non configurata: meglio nessuna fattura che una da 0 euro
        if ($importo <= 0) {
            Log::warning('Fattura automatica saltata: tariffa non impostata.', [
                'appointment_id' => $appointment->id,
                'doctor_id'      => $appointment->doctor_id,
            ]);

            return null;
        }

        $descrizioni = config('billing.descriptions', []);
        $descrizione = $descrizioni[$appointment->type] ?? 'Prestazione sanitaria';
        $data        = $appointment->scheduled_at?->format('d/m/Y') ?? now()->format('d/m/Y');

        try {
            return $this->create([
                'patient_id'     => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'issue_date'     => now()->toDateString(),
                'due_date'       => now()->addDays((int) config('billing.due_days', 30))->toDateString(),
                'tax_rate'       => (float) config('billing.tax_rate', 0),
                'status'         => config('billing.auto_invoice_status', 'emessa'),
                'items'          => [[
                    'description' => "{$descrizione} del {$data}",
                    'quantity'    => 1,
                    'unit_price'  => $importo,
                ]],
            ]);
        } catch (\Throwable $e) {
            // La visita resta chiusa: la fattura si recupera a mano dal Finanziario
            Log::error('Fattura automatica non generata.', [
                'appointment_id' => $appointment->id,
                'errore'         => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Registra un incasso e aggiorna automaticamente lo stato della fattura. */
    public function registerPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $payment = $invoice->payments()->create([
                'amount'          => $data['amount'],
                'method'          => $data['method'],
                'status'          => $data['status'] ?? 'completato',
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'paid_at'         => $data['paid_at'] ?? now(),
            ]);

            $aggiornata = $this->recalculate($invoice);

            // Solo gli incassi effettivi muovono i KPI: un pagamento "in_attesa"
            // (bonifico non ancora accreditato) non deve avvisare nessuno.
            if ($payment->status === 'completato') {
                $this->notifications->paymentRegistered($aggiornata, $payment);
            }

            return $payment;
        });
    }

    /** Ricalcola incassato e stato in base ai pagamenti completati. */
    public function recalculate(Invoice $invoice): Invoice
    {
        $paid = (float) $invoice->payments()->where('status', 'completato')->sum('amount');

        $status = match (true) {
            $paid <= 0 && $invoice->due_date?->isPast() => 'scaduta',
            $paid <= 0                                   => 'emessa',
            $paid + 0.01 >= (float) $invoice->total      => 'pagata',
            default                                      => 'parziale',
        };

        $invoice->update(['paid_amount' => $paid, 'status' => $status]);

        return $invoice->fresh();
    }

    /** Report finanziario per la dashboard admin. */
    public function report(?string $from = null, ?string $to = null): array
    {
        $query = Invoice::period($from, $to);

        $emesso   = (float) (clone $query)->sum('total');
        $incassato = (float) (clone $query)->sum('paid_amount');

        return [
            'fatturato'        => round($emesso, 2),
            'incassato'        => round($incassato, 2),
            'da_incassare'     => round($emesso - $incassato, 2),
            'fatture_totali'   => (clone $query)->count(),
            'fatture_scadute'  => (clone $query)->where('status', 'scaduta')->count(),
            // Il mix incassi deve seguire lo stesso periodo dei KPI accanto,
            // altrimenti mostra il totale storico e non torna con l'incassato.
            'per_metodo'       => Payment::where('status', 'completato')
                                    ->when($from, fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
                                    ->when($to, fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))
                                    ->selectRaw('method, SUM(amount) as totale')
                                    ->groupBy('method')
                                    ->pluck('totale', 'method'),
        ];
    }

    private function nextNumber(): string
    {
        $year = now()->year;

        $last = Invoice::withTrashed()
            ->where('number', 'like', "{$year}/%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('number');

        $next = $last ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('%d/%06d', $year, $next);
    }
}
