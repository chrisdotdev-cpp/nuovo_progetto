<?php

namespace Tests\Feature\Auth;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaScenariSanitari;
use Tests\TestCase;

/**
 * Separazione dei ruoli.
 *
 * In ambito sanitario il rischio non e' il crash ma la fuga di dati: un medico
 * che vede pazienti non suoi, un paziente che apre l'anagrafica di un altro.
 * Qui ogni area sensibile viene provata con tutti e tre i ruoli, verificando
 * sia chi deve entrare sia chi deve essere respinto.
 */
class RuoliEPermessiTest extends TestCase
{
    use RefreshDatabase, CreaScenariSanitari;

    /* =====================================================================
     | Area amministrazione
     * ===================================================================*/

    public function test_solo_l_admin_accede_alla_gestione_utenti(): void
    {
        User::factory()->count(3)->create();

        $this->come($this->admin())->getJson('/api/v1/users')->assertOk();
        $this->comeMedico($this->medico())->getJson('/api/v1/users')->assertForbidden();
        $this->comePaziente($this->paziente())->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_solo_l_admin_crea_nuovi_account(): void
    {
        $nuovo = [
            'name'                  => 'Luca Verdi',
            'email'                 => 'luca.verdi@example.it',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
            'role'                  => User::ROLE_PAZIENTE,
        ];

        $this->comePaziente($this->paziente())->postJson('/api/v1/users', $nuovo)->assertForbidden();
        $this->comeMedico($this->medico())->postJson('/api/v1/users', $nuovo)->assertForbidden();

        $this->come($this->admin())->postJson('/api/v1/users', $nuovo)->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'luca.verdi@example.it', 'role' => User::ROLE_PAZIENTE]);
    }

    public function test_creando_un_medico_nasce_anche_il_profilo_professionale(): void
    {
        $this->come($this->admin())
            ->postJson('/api/v1/users', [
                'name'                  => 'Dott. Paolo Neri',
                'email'                 => 'paolo.neri@example.it',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'role'                  => User::ROLE_MEDICO,
                'specialization'        => 'Ortopedia',
                'consultation_fee'      => 90,
            ])
            ->assertCreated();

        $medico = User::where('email', 'paolo.neri@example.it')->first();

        $this->assertNotNull($medico->doctor, 'Un account medico deve avere il record in `doctors`.');
        $this->assertSame('Ortopedia', $medico->doctor->specialization);
    }

    public function test_un_medico_senza_specializzazione_viene_rifiutato(): void
    {
        $this->come($this->admin())
            ->postJson('/api/v1/users', [
                'name'                  => 'Dott. Senza Specialita',
                'email'                 => 'senza@example.it',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'role'                  => User::ROLE_MEDICO,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('specialization');
    }

    /* =====================================================================
     | Elenco pazienti: il perimetro del medico
     * ===================================================================*/

    public function test_il_paziente_non_puo_sfogliare_l_elenco_pazienti(): void
    {
        $this->comePaziente($this->paziente())
            ->getJson('/api/v1/patients')
            ->assertForbidden();
    }

    public function test_l_admin_vede_tutti_i_pazienti(): void
    {
        $this->paziente();
        $this->paziente();
        $this->paziente();

        $this->come($this->admin())
            ->getJson('/api/v1/patients')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_il_medico_vede_solo_i_propri_assistiti(): void
    {
        $medico = $this->medico();
        $altro  = $this->medico();

        $assegnato = $this->paziente(['primary_doctor_id' => $medico->id]);

        // Non e' suo assistito ma ha una visita con lui: deve comunque vederlo
        $conVisita = $this->paziente();
        Appointment::factory()->create([
            'patient_id' => $conVisita->id,
            'doctor_id'  => $medico->id,
        ]);

        $estraneo = $this->paziente(['primary_doctor_id' => $altro->id]);

        $risposta = $this->comeMedico($medico)->getJson('/api/v1/patients')->assertOk();

        $idVisibili = collect($risposta->json('data'))->pluck('id')->all();

        $this->assertContains($assegnato->id, $idVisibili);
        $this->assertContains($conVisita->id, $idVisibili);
        $this->assertNotContains($estraneo->id, $idVisibili, 'Un medico non deve vedere pazienti di altri.');
    }

    public function test_un_medico_senza_profilo_vede_una_lista_vuota_non_un_errore(): void
    {
        $this->paziente();
        $senzaProfilo = User::factory()->doctor()->create();

        $this->come($senzaProfilo)
            ->getJson('/api/v1/patients')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /* =====================================================================
     | Scheda del paziente autenticato
     * ===================================================================*/

    public function test_patients_me_e_riservato_al_ruolo_paziente(): void
    {
        $this->comeMedico($this->medico())->getJson('/api/v1/patients/me')->assertForbidden();
        $this->come($this->admin())->getJson('/api/v1/patients/me')->assertForbidden();
    }

    public function test_patients_me_restituisce_la_propria_scheda(): void
    {
        $paziente = $this->paziente(['codice_fiscale' => 'RSSMRA80A01H501U']);

        $this->comePaziente($paziente)
            ->getJson('/api/v1/patients/me')
            ->assertOk()
            ->assertJsonPath('data.id', $paziente->id)
            ->assertJsonPath('data.codice_fiscale', 'RSSMRA80A01H501U');
    }

    public function test_un_account_paziente_senza_anagrafica_riceve_un_409_esplicativo(): void
    {
        $orfano = $this->pazienteSenzaProfilo(['email' => 'orfano@example.it']);

        $risposta = $this->come($orfano)
            ->getJson('/api/v1/patients/me')
            ->assertStatus(409);

        // Il messaggio deve dire cosa manca: un 404 generico non e' diagnosticabile
        $this->assertStringContainsString('orfano@example.it', $risposta->json('message'));
    }

    /* =====================================================================
     | Dashboard: un endpoint, tre contenuti
     * ===================================================================*/

    public function test_la_dashboard_del_paziente_espone_le_sue_sezioni(): void
    {
        $paziente = $this->paziente();

        $this->comePaziente($paziente)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_PAZIENTE)
            ->assertJsonStructure(['role', 'data' => ['stats', 'appuntamenti', 'documenti']]);
    }

    public function test_la_dashboard_del_medico_espone_agenda_e_richieste(): void
    {
        $medico = $this->medico();

        Appointment::factory()->create([
            'doctor_id'    => $medico->id,
            'patient_id'   => $this->paziente()->id,
            'scheduled_at' => now()->setTime(11, 0),
        ]);

        $this->comeMedico($medico)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_MEDICO)
            ->assertJsonStructure(['data' => ['stats', 'agenda_oggi', 'richieste']])
            ->assertJsonPath('data.stats.appuntamenti_oggi', 1)
            ->assertJsonCount(1, 'data.agenda_oggi');
    }

    public function test_la_dashboard_dell_admin_espone_i_totali_di_struttura(): void
    {
        $this->medico();
        $this->paziente();
        $this->paziente();

        $risposta = $this->come($this->admin())
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_ADMIN)
            ->assertJsonStructure(['data' => ['stats' => ['utenti_totali', 'pazienti', 'medici'], 'appuntamenti_oggi']]);

        $this->assertSame(1, $risposta->json('data.stats.medici'));
        $this->assertSame(2, $risposta->json('data.stats.pazienti'));
    }

    public function test_la_dashboard_richiede_autenticazione(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    /* =====================================================================
     | Notifiche: ognuno vede solo le proprie
     * ===================================================================*/

    public function test_le_notifiche_sono_isolate_per_utente(): void
    {
        $mio  = $this->paziente();
        $suo  = $this->paziente();

        AppNotification::factory()->count(2)->create(['user_id' => $mio->user_id]);
        AppNotification::factory()->create(['user_id' => $suo->user_id]);

        $this->comePaziente($mio)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->comePaziente($mio)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_non_si_puo_leggere_la_notifica_di_un_altro_utente(): void
    {
        $mio      = $this->paziente();
        $altrui   = AppNotification::factory()->create(['user_id' => $this->paziente()->user_id]);

        $this->comePaziente($mio)
            ->postJson("/api/v1/notifications/{$altrui->id}/read")
            ->assertForbidden();

        $this->assertNull($altrui->fresh()->read_at);
    }

    public function test_segna_tutte_come_lette(): void
    {
        $paziente = $this->paziente();
        AppNotification::factory()->count(3)->create(['user_id' => $paziente->user_id]);

        $this->comePaziente($paziente)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated', 3);

        $this->comePaziente($paziente)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertJsonPath('count', 0);
    }
}
