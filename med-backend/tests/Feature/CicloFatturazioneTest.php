<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppNotification;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciclo completo: appuntamento completato -> fattura -> incasso -> dashboard.
 *
 * E' il percorso che tocca tre moduli diversi (agenda, contabilita', panoramiche)
 * e nessuno dei tre se ne accorge se si rompe: qui viene verificato end-to-end.
 */
class CicloFatturazioneTest extends TestCase
{
    use RefreshDatabase;

    private function appuntamento(float $tariffa = 80): Appointment
    {
        return Appointment::factory()->create([
            'doctor_id'  => Doctor::factory()->create(['consultation_fee' => $tariffa])->id,
            'patient_id' => Patient::factory()->create()->id,
            'type'       => 'visita',
        ]);
    }

    /* ---------------------------------------------------------------------
     | Emissione automatica
     * -------------------------------------------------------------------*/

    public function test_completare_un_appuntamento_emette_la_fattura(): void
    {
        $appointment = $this->appuntamento(80);

        $this->assertNull($appointment->invoice);

        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $invoice = $appointment->fresh()->invoice;

        $this->assertNotNull($invoice, 'La chiusura della visita deve generare la fattura.');
        $this->assertSame('emessa', $invoice->status);
        $this->assertEquals(80.0, (float) $invoice->total);
        $this->assertEquals(0.0, (float) $invoice->paid_amount);
        $this->assertEquals(80.0, $invoice->balance);
        $this->assertCount(1, $invoice->items);
    }

    public function test_la_fattura_non_viene_duplicata(): void
    {
        $appointment = $this->appuntamento();

        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);
        // Ricompletare (doppio click, riallineamento admin) non deve rifatturare
        $appointment->update(['status' => Appointment::STATUS_CONFERMATO]);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $this->assertSame(1, Invoice::where('appointment_id', $appointment->id)->count());
    }

    public function test_nessuna_fattura_se_il_medico_non_ha_tariffa(): void
    {
        $appointment = $this->appuntamento(0);

        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $this->assertSame(0, Invoice::count());
        // La visita resta comunque chiusa: la contabilita' non blocca la clinica
        $this->assertSame(Appointment::STATUS_COMPLETATO, $appointment->fresh()->status);
    }

    public function test_annullare_un_appuntamento_non_emette_fattura(): void
    {
        $appointment = $this->appuntamento();

        $appointment->update(['status' => Appointment::STATUS_ANNULLATO]);

        $this->assertSame(0, Invoice::count());
    }

    public function test_il_paziente_viene_avvisato_della_fattura(): void
    {
        $appointment = $this->appuntamento();

        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id'  => $appointment->patient->user_id,
            'title'    => 'Nuova fattura da saldare',
            'category' => 'pagamento',
        ]);
    }

    /* ---------------------------------------------------------------------
     | Incasso
     * -------------------------------------------------------------------*/

    public function test_saldo_totale_porta_la_fattura_a_pagata(): void
    {
        $appointment = $this->appuntamento(100);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $invoice = $appointment->fresh()->invoice;

        app(InvoiceService::class)->registerPayment($invoice, [
            'amount' => 100,
            'method' => 'carta',
        ]);

        $invoice->refresh();

        $this->assertSame('pagata', $invoice->status);
        $this->assertEquals(100.0, (float) $invoice->paid_amount);
        $this->assertEquals(0.0, $invoice->balance);
    }

    public function test_acconto_porta_la_fattura_a_parziale(): void
    {
        $appointment = $this->appuntamento(100);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $invoice = $appointment->fresh()->invoice;

        app(InvoiceService::class)->registerPayment($invoice, [
            'amount' => 40,
            'method' => 'contanti',
        ]);

        $invoice->refresh();

        $this->assertSame('parziale', $invoice->status);
        $this->assertEquals(60.0, $invoice->balance);
    }

    public function test_l_amministrazione_viene_avvisata_dell_incasso(): void
    {
        $admin       = User::factory()->admin()->create();
        $appointment = $this->appuntamento(100);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        app(InvoiceService::class)->registerPayment($appointment->fresh()->invoice, [
            'amount' => 100,
            'method' => 'bonifico',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'title'   => 'Pagamento ricevuto',
        ]);
    }

    public function test_un_pagamento_in_attesa_non_notifica_e_non_salda(): void
    {
        $admin       = User::factory()->admin()->create();
        $appointment = $this->appuntamento(100);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $invoice = $appointment->fresh()->invoice;

        // Bonifico disposto ma non ancora accreditato
        app(InvoiceService::class)->registerPayment($invoice, [
            'amount' => 100,
            'method' => 'bonifico',
            'status' => 'in_attesa',
        ]);

        $this->assertNotSame('pagata', $invoice->refresh()->status);
        $this->assertSame(0, AppNotification::where('user_id', $admin->id)
            ->where('title', 'Pagamento ricevuto')->count());
    }

    /* ---------------------------------------------------------------------
     | Ricaduta sulle dashboard
     * -------------------------------------------------------------------*/

    public function test_la_dashboard_admin_riflette_l_incasso(): void
    {
        $admin       = User::factory()->admin()->create();
        $appointment = $this->appuntamento(100);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        $dashboard = app(DashboardService::class);

        $prima = $dashboard->forUser($admin)['stats'];
        $this->assertEquals(100.0, $prima['fatturato_mese']);
        $this->assertEquals(100.0, $prima['da_incassare']);
        $this->assertEquals(0.0, $prima['incassato_mese']);
        $this->assertSame(1, $prima['fatture_da_saldare']);

        app(InvoiceService::class)->registerPayment($appointment->fresh()->invoice, [
            'amount' => 100,
            'method' => 'carta',
        ]);

        $dopo = $dashboard->forUser($admin)['stats'];
        $this->assertEquals(100.0, $dopo['incassato_mese']);
        $this->assertEquals(0.0, $dopo['da_incassare']);
        $this->assertSame(0, $dopo['fatture_da_saldare']);
    }

    /**
     * Regressione: "da incassare" sommava il totale lordo delle fatture aperte,
     * quindi dopo un acconto il KPI restava fermo.
     */
    public function test_da_incassare_tiene_conto_degli_acconti(): void
    {
        $admin       = User::factory()->admin()->create();
        $appointment = $this->appuntamento(100);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        app(InvoiceService::class)->registerPayment($appointment->fresh()->invoice, [
            'amount' => 40,
            'method' => 'contanti',
        ]);

        $stats = app(DashboardService::class)->forUser($admin)['stats'];

        $this->assertEquals(60.0, $stats['da_incassare'], 'Deve restare lo scoperto, non il lordo.');
        $this->assertEquals(40.0, $stats['incassato_mese']);
    }

    public function test_la_dashboard_paziente_mostra_il_residuo(): void
    {
        $appointment = $this->appuntamento(100);
        $appointment->update(['status' => Appointment::STATUS_COMPLETATO]);

        app(InvoiceService::class)->registerPayment($appointment->fresh()->invoice, [
            'amount' => 30,
            'method' => 'carta',
        ]);

        $stats = app(DashboardService::class)->forUser($appointment->patient->user)['stats'];

        $this->assertEquals(70.0, $stats['da_pagare']);
        $this->assertSame(1, $stats['fatture_aperte']);
    }
}
