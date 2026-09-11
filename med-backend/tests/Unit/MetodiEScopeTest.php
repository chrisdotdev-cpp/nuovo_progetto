<?php

namespace Tests\Unit;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaScenariSanitari;
use Tests\TestCase;

/**
 * Metodi, accessor e scope dei model.
 *
 * Sono le regole di dominio scritte una volta sola e usate ovunque: se
 * isEditable() o lo scope between() sbagliano, sbagliano contemporaneamente
 * agenda, dashboard e permessi.
 */
class MetodiEScopeTest extends TestCase
{
    use RefreshDatabase, CreaScenariSanitari;

    /* =====================================================================
     | User
     * ===================================================================*/

    public function test_gli_helper_di_ruolo_si_escludono_a_vicenda(): void
    {
        $admin    = User::factory()->admin()->make();
        $medico   = User::factory()->doctor()->make();
        $paziente = User::factory()->make();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isDoctor());
        $this->assertFalse($admin->isPatient());

        $this->assertTrue($medico->isDoctor());
        $this->assertFalse($medico->isAdmin());

        $this->assertTrue($paziente->isPatient());
        $this->assertFalse($paziente->isAdmin());
    }

    public function test_solo_lo_stato_attivo_conta_come_attivo(): void
    {
        $this->assertTrue(User::factory()->make(['status' => 'attivo'])->isActive());
        $this->assertFalse(User::factory()->make(['status' => 'sospeso'])->isActive());
        $this->assertFalse(User::factory()->make(['status' => 'in_attesa'])->isActive());
    }

    public function test_gli_scope_ruolo_e_attivo_filtrano_gli_account(): void
    {
        User::factory()->count(2)->admin()->create();
        User::factory()->doctor()->create();
        User::factory()->count(3)->create();
        User::factory()->suspended()->create();

        $this->assertSame(2, User::role(User::ROLE_ADMIN)->count());
        $this->assertSame(1, User::role(User::ROLE_MEDICO)->count());
        $this->assertSame(6, User::active()->count());
    }

    public function test_la_ricerca_utenti_guarda_nome_ed_email(): void
    {
        User::factory()->create(['name' => 'Giulia Ferrari', 'email' => 'giulia@example.it']);
        User::factory()->create(['name' => 'Marco Rossi', 'email' => 'marco.rossi@clinica.it']);

        $this->assertSame(1, User::search('Ferrari')->count());
        $this->assertSame(1, User::search('clinica.it')->count());
        $this->assertSame(0, User::search('inesistente')->count());

        // Termine vuoto: lo scope non deve filtrare nulla
        $this->assertSame(2, User::search(null)->count());
        $this->assertSame(2, User::search('')->count());
    }

    /* =====================================================================
     | Patient
     * ===================================================================*/

    public function test_l_eta_deriva_dalla_data_di_nascita(): void
    {
        $paziente = Patient::factory()->make(['birth_date' => now()->subYears(35)->subMonth()->toDateString()]);

        $this->assertSame(35, $paziente->age);
        $this->assertNull(Patient::factory()->make(['birth_date' => null])->age);
    }

    public function test_allergie_e_patologie_sono_liste_non_stringhe(): void
    {
        $paziente = $this->paziente([
            'allergies'          => ['Polline', 'Nichel'],
            'chronic_conditions' => ['Asma'],
        ]);

        $riletto = $paziente->fresh();

        $this->assertSame(['Polline', 'Nichel'], $riletto->allergies);
        $this->assertSame(['Asma'], $riletto->chronic_conditions);
    }

    public function test_la_ricerca_pazienti_copre_codice_fiscale_nome_ed_email(): void
    {
        $this->paziente(['codice_fiscale' => 'BNCLRA90D45F205X'], ['name' => 'Laura Bianchi']);
        $this->paziente(['codice_fiscale' => 'VRDGPP75L10H501K'], ['name' => 'Giuseppe Verdi']);

        $this->assertSame(1, Patient::search('BNCLRA90D45F205X')->count());
        $this->assertSame(1, Patient::search('Giuseppe')->count());
        $this->assertSame(2, Patient::search(null)->count());
    }

    public function test_il_perimetro_del_medico_include_assistiti_e_pazienti_visitati(): void
    {
        $medico = $this->medico();

        $assistito = $this->paziente(['primary_doctor_id' => $medico->id]);
        $visitato  = $this->paziente();
        $estraneo  = $this->paziente();

        Appointment::factory()->create(['doctor_id' => $medico->id, 'patient_id' => $visitato->id]);

        $ids = Patient::ofDoctor($medico->id)->pluck('id')->all();

        $this->assertContains($assistito->id, $ids);
        $this->assertContains($visitato->id, $ids);
        $this->assertNotContains($estraneo->id, $ids);
    }

    /**
     * Regressione: PatientController passa `$user->doctor?->id`, che e' null per
     * un account medico senza record in `doctors`. Lo scope deve rispondere con
     * una lista vuota, non con un TypeError (500 sull'elenco pazienti).
     */
    public function test_il_perimetro_di_un_medico_senza_profilo_e_vuoto(): void
    {
        $this->paziente();
        $this->paziente();

        $this->assertSame(0, Patient::ofDoctor(null)->count());
    }

    /* =====================================================================
     | Doctor
     * ===================================================================*/

    public function test_gli_scope_del_medico_filtrano_specializzazione_e_telemedicina(): void
    {
        $this->medico(['specialization' => 'Cardiologia', 'available_online' => true]);
        $this->medico(['specialization' => 'Ortopedia', 'available_online' => false]);

        $this->assertSame(1, Doctor::specialization('Cardiologia')->count());
        $this->assertSame(2, Doctor::specialization(null)->count(), 'Filtro vuoto = nessun filtro.');
        $this->assertSame(1, Doctor::onlineEnabled()->count());
    }

    /* =====================================================================
     | Appointment
     * ===================================================================*/

    public function test_l_orario_di_fine_si_calcola_dalla_durata(): void
    {
        $inizio = $this->slotFuturo(10, 0);

        $appuntamento = Appointment::factory()->make([
            'scheduled_at'     => $inizio,
            'duration_minutes' => 45,
        ]);

        $this->assertSame($inizio->addMinutes(45)->toIso8601String(), $appuntamento->ends_at);
    }

    public function test_senza_durata_si_assume_mezz_ora(): void
    {
        $inizio = $this->slotFuturo(10, 0);

        $appuntamento = Appointment::factory()->make([
            'scheduled_at'     => $inizio,
            'duration_minutes' => null,
        ]);

        $this->assertSame($inizio->addMinutes(30)->toIso8601String(), $appuntamento->ends_at);
    }

    public function test_un_appuntamento_e_modificabile_finche_non_e_chiuso(): void
    {
        $modificabili = [
            Appointment::STATUS_IN_ATTESA,
            Appointment::STATUS_CONFERMATO,
            Appointment::STATUS_ASSENTE,
        ];

        foreach ($modificabili as $stato) {
            $this->assertTrue(
                Appointment::factory()->make(['status' => $stato])->isEditable(),
                "Lo stato {$stato} dovrebbe restare modificabile."
            );
        }

        foreach ([Appointment::STATUS_COMPLETATO, Appointment::STATUS_ANNULLATO] as $stato) {
            $this->assertFalse(
                Appointment::factory()->make(['status' => $stato])->isEditable(),
                "Lo stato {$stato} deve chiudere l'appuntamento."
            );
        }
    }

    public function test_gli_scope_upcoming_e_past_dividono_l_agenda(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $futuro = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => now()->addDays(2),
        ]);

        $passato = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => now()->subDays(2),
        ]);

        // Futuro ma annullato: non e' "in arrivo"
        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => now()->addDays(3),
            'status' => Appointment::STATUS_ANNULLATO,
        ]);

        $this->assertSame([$futuro->id], Appointment::upcoming()->pluck('id')->all());
        $this->assertSame([$passato->id], Appointment::past()->pluck('id')->all());
    }

    /**
     * Regressione: filtrando un solo giorno il frontend invia from = to senza
     * orario. Interpretati alla lettera erano entrambi mezzanotte e l'intervallo
     * intercettava solo gli appuntamenti fissati a mezzanotte esatta: nessuno.
     */
    public function test_l_intervallo_espresso_in_sole_date_copre_tutta_la_giornata(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();
        $giorno   = $this->slotFuturo(0, 0)->toDateString();

        $mattina = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => $giorno.' 08:15:00',
        ]);

        $sera = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => $giorno.' 19:45:00',
        ]);

        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => $this->slotFuturo(10, 0, 5),
        ]);

        $trovati = Appointment::between($giorno, $giorno)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$mattina->id, $sera->id], $trovati);
    }

    public function test_l_intervallo_con_orario_resta_preciso_al_minuto(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();
        $giorno   = $this->slotFuturo(0, 0)->toDateString();

        $dentro = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => $giorno.' 10:00:00',
        ]);

        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'scheduled_at' => $giorno.' 15:00:00',
        ]);

        $trovati = Appointment::between($giorno.' 09:00:00', $giorno.' 11:00:00')->pluck('id')->all();

        $this->assertSame([$dentro->id], $trovati);
    }

    public function test_lo_scope_stato_ignora_i_filtri_vuoti(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'status' => Appointment::STATUS_CONFERMATO,
        ]);
        Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
            'status' => Appointment::STATUS_ANNULLATO,
        ]);

        $this->assertSame(1, Appointment::status(Appointment::STATUS_CONFERMATO)->count());
        $this->assertSame(2, Appointment::status(null)->count());
    }

    /* =====================================================================
     | AppNotification
     * ===================================================================*/

    public function test_una_notifica_letta_non_cambia_piu_timestamp(): void
    {
        $notifica = AppNotification::factory()->create(['user_id' => User::factory()]);

        $this->assertFalse($notifica->is_read);

        $notifica->markAsRead();
        $primaLettura = $notifica->fresh()->read_at;

        $this->assertNotNull($primaLettura);
        $this->assertTrue($notifica->fresh()->is_read);

        $this->travel(5)->minutes();
        $notifica->fresh()->markAsRead();

        $this->assertEquals(
            $primaLettura,
            $notifica->fresh()->read_at,
            'Rileggere una notifica non deve spostare la data di lettura.'
        );
    }

    public function test_gli_scope_delle_notifiche_filtrano_lette_e_categoria(): void
    {
        $user = User::factory()->create();

        AppNotification::factory()->count(2)->create(['user_id' => $user->id]);
        AppNotification::factory()->letta()->create(['user_id' => $user->id]);
        AppNotification::factory()->categoria('appuntamento')->create(['user_id' => $user->id]);

        $this->assertSame(3, AppNotification::unread()->count());
        $this->assertSame(1, AppNotification::category('appuntamento')->count());
        $this->assertSame(4, AppNotification::category(null)->count());
    }
}
