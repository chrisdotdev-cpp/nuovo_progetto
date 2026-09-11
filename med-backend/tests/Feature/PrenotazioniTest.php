<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorAbsence;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaScenariSanitari;
use Tests\TestCase;

/**
 * Prenotazione visite: il percorso piu' usato dell'applicazione.
 *
 * Le regole di slot stanno tutte in AppointmentService e non nel database, per
 * poter restituire messaggi comprensibili. Di conseguenza sono verificabili
 * solo passando dall'endpoint reale: e' quello che fa questa classe.
 */
class PrenotazioniTest extends TestCase
{
    use RefreshDatabase, CreaScenariSanitari;

    private const APPUNTAMENTI = '/api/v1/appointments';

    /* =====================================================================
     | Creazione
     * ===================================================================*/

    public function test_il_paziente_prenota_una_visita(): void
    {
        $medico   = $this->medico(['slot_duration' => 30]);
        $paziente = $this->paziente();
        $quando   = $this->slotFuturo(10, 0);

        $this->comePaziente($paziente)
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $quando->toIso8601String(),
                'type'         => 'visita',
                'reason'       => 'Controllo pressione',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Appuntamento prenotato.')
            ->assertJsonPath('data.doctor_id', $medico->id)
            ->assertJsonPath('data.patient_id', $paziente->id)
            ->assertJsonPath('data.status', Appointment::STATUS_IN_ATTESA)
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.reason', 'Controllo pressione');

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $paziente->id,
            'doctor_id'  => $medico->id,
            'status'     => Appointment::STATUS_IN_ATTESA,
        ]);
    }

    public function test_la_durata_di_default_e_quella_dello_slot_del_medico(): void
    {
        $medico   = $this->medico(['slot_duration' => 45]);
        $paziente = $this->paziente();

        $this->comePaziente($paziente)
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $this->slotFuturo(9, 0)->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.duration_minutes', 45);
    }

    public function test_il_paziente_non_puo_prenotare_a_nome_di_un_altro(): void
    {
        $medico    = $this->medico();
        $io        = $this->paziente();
        $qualcuno  = $this->paziente();

        $this->comePaziente($io)
            ->postJson(self::APPUNTAMENTI, [
                'patient_id'   => $qualcuno->id,   // tentativo di prenotare per terzi
                'doctor_id'    => $medico->id,
                'scheduled_at' => $this->slotFuturo(11, 0)->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $io->id);

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseMissing('appointments', ['patient_id' => $qualcuno->id]);
    }

    public function test_la_segreteria_prenota_per_conto_del_paziente(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $this->come($this->admin())
            ->postJson(self::APPUNTAMENTI, [
                'patient_id'   => $paziente->id,
                'doctor_id'    => $medico->id,
                'scheduled_at' => $this->slotFuturo(12, 0)->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $paziente->id);
    }

    public function test_un_account_paziente_senza_anagrafica_non_puo_prenotare(): void
    {
        $medico = $this->medico();
        $orfano = $this->pazienteSenzaProfilo();

        $this->come($orfano)
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $this->slotFuturo()->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    /* =====================================================================
     | Regole di disponibilita'
     * ===================================================================*/

    public function test_non_si_prenota_fuori_dall_orario_del_medico(): void
    {
        $medico = $this->medico([], [], conOrario: false);
        $quando = $this->slotFuturo(15, 0);

        // Il medico lavora solo la mattina di quel giorno
        DoctorSchedule::factory()
            ->weekday($quando->dayOfWeekIso)
            ->fascia('09:00:00', '11:00:00')
            ->create(['doctor_id' => $medico->id]);

        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $quando->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_una_fascia_disattivata_non_rende_prenotabile_lo_slot(): void
    {
        $medico = $this->medico([], [], conOrario: false);
        $quando = $this->slotFuturo(10, 0);

        DoctorSchedule::factory()
            ->weekday($quando->dayOfWeekIso)
            ->fascia('08:00:00', '20:00:00')
            ->inattiva()
            ->create(['doctor_id' => $medico->id]);

        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $quando->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');
    }

    public function test_due_pazienti_non_possono_occupare_lo_stesso_slot(): void
    {
        $medico = $this->medico(['slot_duration' => 30]);
        $quando = $this->slotFuturo(10, 0);

        Appointment::factory()->create([
            'doctor_id'        => $medico->id,
            'patient_id'       => $this->paziente()->id,
            'scheduled_at'     => $quando,
            'duration_minutes' => 30,
        ]);

        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $quando->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_anche_una_sovrapposizione_parziale_viene_respinta(): void
    {
        $medico = $this->medico(['slot_duration' => 60]);
        $quando = $this->slotFuturo(10, 0);

        Appointment::factory()->create([
            'doctor_id'        => $medico->id,
            'patient_id'       => $this->paziente()->id,
            'scheduled_at'     => $quando,
            'duration_minutes' => 60,
        ]);

        // Inizia mezz'ora dopo: si accavalla sulla seconda meta' della visita
        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $quando->addMinutes(30)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');
    }

    public function test_uno_slot_liberato_da_un_annullamento_torna_prenotabile(): void
    {
        $medico = $this->medico(['slot_duration' => 30]);
        $quando = $this->slotFuturo(10, 0);

        Appointment::factory()->create([
            'doctor_id'        => $medico->id,
            'patient_id'       => $this->paziente()->id,
            'scheduled_at'     => $quando,
            'duration_minutes' => 30,
            'status'           => Appointment::STATUS_ANNULLATO,
        ]);

        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $quando->toIso8601String(),
            ])
            ->assertCreated();
    }

    public function test_non_si_prenota_nel_passato(): void
    {
        $medico = $this->medico();

        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => now()->subDay()->setTime(10, 0)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');
    }

    public function test_non_si_prenota_con_il_medico_in_ferie(): void
    {
        $medico = $this->medico();
        $quando = $this->slotFuturo(10, 0);

        DoctorAbsence::factory()->nelGiorno($quando->toDateString())->create(['doctor_id' => $medico->id]);

        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $quando->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');
    }

    public function test_il_medico_deve_esistere(): void
    {
        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => 999999,
                'scheduled_at' => $this->slotFuturo()->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('doctor_id');
    }

    /* =====================================================================
     | Effetti collaterali della prenotazione
     * ===================================================================*/

    public function test_la_prenotazione_avvisa_sia_il_paziente_sia_il_medico(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $this->comePaziente($paziente)
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $this->slotFuturo(10, 0)->toIso8601String(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('app_notifications', [
            'user_id'  => $paziente->user_id,
            'category' => 'appuntamento',
            'title'    => 'Appuntamento prenotato',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id'  => $medico->user_id,
            'category' => 'appuntamento',
            'title'    => 'Nuovo appuntamento in agenda',
        ]);
    }

    public function test_un_teleconsulto_apre_la_stanza_virtuale(): void
    {
        $medico   = $this->medico(['available_online' => true]);
        $paziente = $this->paziente();

        $this->comePaziente($paziente)
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $this->slotFuturo(10, 0)->toIso8601String(),
                'type'         => 'telemedicina',
            ])
            ->assertCreated();

        $appuntamento = Appointment::first();

        $this->assertNotNull(
            $appuntamento->telemedicineSession,
            'Un appuntamento di telemedicina deve avere la sessione collegata.'
        );
    }

    /* =====================================================================
     | Elenco filtrato per ruolo
     * ===================================================================*/

    public function test_ogni_ruolo_vede_solo_la_propria_agenda(): void
    {
        $medico  = $this->medico();
        $collega = $this->medico();

        $mio    = $this->paziente();
        $altrui = $this->paziente();

        $suoMio     = Appointment::factory()->create(['doctor_id' => $medico->id,  'patient_id' => $mio->id]);
        $suoAltrui  = Appointment::factory()->create(['doctor_id' => $medico->id,  'patient_id' => $altrui->id]);
        $delCollega = Appointment::factory()->create(['doctor_id' => $collega->id, 'patient_id' => $altrui->id]);

        // Paziente: solo i suoi
        $daPaziente = $this->comePaziente($mio)->getJson(self::APPUNTAMENTI)->assertOk();
        $this->assertSame([$suoMio->id], collect($daPaziente->json('data'))->pluck('id')->all());

        // Medico: solo quelli della propria agenda
        $daMedico = $this->comeMedico($medico)->getJson(self::APPUNTAMENTI)->assertOk();
        $idMedico = collect($daMedico->json('data'))->pluck('id')->all();
        $this->assertContains($suoMio->id, $idMedico);
        $this->assertContains($suoAltrui->id, $idMedico);
        $this->assertNotContains($delCollega->id, $idMedico);

        // Admin: tutti
        $this->come($this->admin())
            ->getJson(self::APPUNTAMENTI)
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_l_elenco_si_filtra_per_stato(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'status' => Appointment::STATUS_CONFERMATO,
        ]);
        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'status' => Appointment::STATUS_ANNULLATO, 'scheduled_at' => now()->addDays(2)->setTime(9, 0),
        ]);

        $this->comePaziente($paziente)
            ->getJson(self::APPUNTAMENTI.'?status='.Appointment::STATUS_CONFERMATO)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', Appointment::STATUS_CONFERMATO);
    }

    /**
     * Regressione: filtrando un singolo giorno il frontend invia from = to senza
     * orario. Interpretati alla lettera diventano entrambi mezzanotte e l'agenda
     * del medico restava vuota anche subito dopo una prenotazione riuscita.
     */
    public function test_il_filtro_su_un_solo_giorno_copre_l_intera_giornata(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();
        $giorno   = $this->slotFuturo(14, 30);

        Appointment::factory()->create([
            'doctor_id'    => $medico->id,
            'patient_id'   => $paziente->id,
            'scheduled_at' => $giorno,
        ]);

        // Appuntamento del giorno dopo: non deve entrare nel filtro
        Appointment::factory()->create([
            'doctor_id'    => $medico->id,
            'patient_id'   => $paziente->id,
            'scheduled_at' => $giorno->addDay(),
        ]);

        $data = $giorno->toDateString();

        $this->comeMedico($medico)
            ->getJson(self::APPUNTAMENTI."?from={$data}&to={$data}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.time', '14:30');
    }

    public function test_l_elenco_si_filtra_per_prossimi_appuntamenti(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $futuro = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => now()->subDays(3)->setTime(10, 0),
        ]);

        $this->comePaziente($paziente)
            ->getJson(self::APPUNTAMENTI.'?scope=upcoming')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $futuro->id);
    }

    public function test_le_note_interne_non_sono_visibili_al_paziente(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        Appointment::factory()->create([
            'doctor_id'  => $medico->id,
            'patient_id' => $paziente->id,
            'notes'      => 'Sospetto diagnostico da approfondire',
        ]);

        $daPaziente = $this->comePaziente($paziente)->getJson(self::APPUNTAMENTI)->assertOk();
        $this->assertArrayNotHasKey('notes', $daPaziente->json('data.0'));

        $daMedico = $this->comeMedico($medico)->getJson(self::APPUNTAMENTI)->assertOk();
        $this->assertSame('Sospetto diagnostico da approfondire', $daMedico->json('data.0.notes'));
    }

    /* =====================================================================
     | Lettura del singolo appuntamento
     * ===================================================================*/

    public function test_un_estraneo_non_puo_aprire_un_appuntamento(): void
    {
        $appuntamento = Appointment::factory()->create([
            'doctor_id'  => $this->medico()->id,
            'patient_id' => $this->paziente()->id,
        ]);

        $this->comePaziente($this->paziente())
            ->getJson(self::APPUNTAMENTI."/{$appuntamento->id}")
            ->assertForbidden();

        $this->comeMedico($this->medico())
            ->getJson(self::APPUNTAMENTI."/{$appuntamento->id}")
            ->assertForbidden();
    }

    public function test_i_partecipanti_e_l_admin_aprono_l_appuntamento(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->comePaziente($paziente)->getJson(self::APPUNTAMENTI."/{$appuntamento->id}")->assertOk();
        $this->comeMedico($medico)->getJson(self::APPUNTAMENTI."/{$appuntamento->id}")->assertOk();
        $this->come($this->admin())->getJson(self::APPUNTAMENTI."/{$appuntamento->id}")->assertOk();
    }

    /* =====================================================================
     | Annullamento e cambio di stato
     * ===================================================================*/

    public function test_il_paziente_annulla_la_propria_visita(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->comePaziente($paziente)
            ->postJson(self::APPUNTAMENTI."/{$appuntamento->id}/cancel", ['reason' => 'Imprevisto di lavoro'])
            ->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_ANNULLATO)
            ->assertJsonPath('data.cancellation_reason', 'Imprevisto di lavoro');

        $this->assertDatabaseHas('appointments', [
            'id'           => $appuntamento->id,
            'status'       => Appointment::STATUS_ANNULLATO,
            'cancelled_by' => $paziente->user_id,
        ]);
    }

    public function test_non_si_annulla_l_appuntamento_di_un_altro(): void
    {
        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $this->medico()->id, 'patient_id' => $this->paziente()->id,
        ]);

        $this->comePaziente($this->paziente())
            ->postJson(self::APPUNTAMENTI."/{$appuntamento->id}/cancel")
            ->assertForbidden();

        $this->assertSame(Appointment::STATUS_CONFERMATO, $appuntamento->fresh()->status);
    }

    public function test_un_appuntamento_annullato_non_si_annulla_di_nuovo(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id,
            'patient_id' => $paziente->id,
            'status' => Appointment::STATUS_ANNULLATO,
        ]);

        $this->comePaziente($paziente)
            ->postJson(self::APPUNTAMENTI."/{$appuntamento->id}/cancel")
            ->assertForbidden();
    }

    public function test_solo_medico_o_admin_dichiarano_conclusa_la_visita(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->comePaziente($paziente)
            ->putJson(self::APPUNTAMENTI."/{$appuntamento->id}", ['status' => Appointment::STATUS_COMPLETATO])
            ->assertForbidden();

        $this->comeMedico($medico)
            ->putJson(self::APPUNTAMENTI."/{$appuntamento->id}", ['status' => Appointment::STATUS_COMPLETATO])
            ->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_COMPLETATO);
    }

    public function test_la_conferma_registra_il_momento_in_cui_avviene(): void
    {
        $medico = $this->medico();

        $appuntamento = Appointment::factory()->create([
            'doctor_id'  => $medico->id,
            'patient_id' => $this->paziente()->id,
            'status'     => Appointment::STATUS_IN_ATTESA,
        ]);

        $this->comeMedico($medico)
            ->putJson(self::APPUNTAMENTI."/{$appuntamento->id}", ['status' => Appointment::STATUS_CONFERMATO])
            ->assertOk();

        $this->assertNotNull($appuntamento->fresh()->confirmed_at);
    }

    public function test_una_visita_conclusa_non_e_piu_modificabile(): void
    {
        $medico = $this->medico();

        $appuntamento = Appointment::factory()->completato()->create([
            'doctor_id'  => $medico->id,
            'patient_id' => $this->paziente()->id,
        ]);

        $this->comeMedico($medico)
            ->putJson(self::APPUNTAMENTI."/{$appuntamento->id}", ['reason' => 'Modifica tardiva'])
            ->assertForbidden();
    }

    public function test_lo_spostamento_ricontrolla_la_disponibilita(): void
    {
        $medico   = $this->medico(['slot_duration' => 30]);
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id'    => $medico->id,
            'patient_id'   => $paziente->id,
            'scheduled_at' => $this->slotFuturo(10, 0),
        ]);

        $occupato = $this->slotFuturo(11, 0);
        Appointment::factory()->create([
            'doctor_id'        => $medico->id,
            'patient_id'       => $this->paziente()->id,
            'scheduled_at'     => $occupato,
            'duration_minutes' => 30,
        ]);

        // Spostamento su uno slot gia' occupato: rifiutato
        $this->comeMedico($medico)
            ->putJson(self::APPUNTAMENTI."/{$appuntamento->id}", ['scheduled_at' => $occupato->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');

        // Spostamento su uno slot libero: accettato, e torna in attesa di conferma
        $libero = $this->slotFuturo(16, 0);

        $this->comeMedico($medico)
            ->putJson(self::APPUNTAMENTI."/{$appuntamento->id}", ['scheduled_at' => $libero->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_IN_ATTESA)
            ->assertJsonPath('data.time', '16:00');
    }

    public function test_solo_l_admin_elimina_un_appuntamento(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->comePaziente($paziente)->deleteJson(self::APPUNTAMENTI."/{$appuntamento->id}")->assertForbidden();
        $this->comeMedico($medico)->deleteJson(self::APPUNTAMENTI."/{$appuntamento->id}")->assertForbidden();

        $this->come($this->admin())->deleteJson(self::APPUNTAMENTI."/{$appuntamento->id}")->assertOk();

        $this->assertSoftDeleted('appointments', ['id' => $appuntamento->id]);
    }

    public function test_l_agenda_richiede_autenticazione(): void
    {
        $this->getJson(self::APPUNTAMENTI)->assertUnauthorized();
        $this->postJson(self::APPUNTAMENTI, [])->assertUnauthorized();
    }

    public function test_un_utente_sospeso_non_puo_prenotare(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();
        $token    = $this->tokenDi($paziente->user);

        $paziente->user->update(['status' => 'sospeso']);

        $this->conToken($token)
            ->postJson(self::APPUNTAMENTI, [
                'doctor_id'    => $medico->id,
                'scheduled_at' => $this->slotFuturo()->toIso8601String(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('appointments', 0);
    }
}
