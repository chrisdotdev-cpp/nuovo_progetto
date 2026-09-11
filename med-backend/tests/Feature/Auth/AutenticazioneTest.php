<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaScenariSanitari;
use Tests\TestCase;

/**
 * Ciclo di vita della sessione: login, me, refresh, logout.
 *
 * L'autenticazione e' a token Sanctum (Bearer) e il backend decide anche la
 * rotta di atterraggio del frontend: se questa parte si rompe l'intera SPA
 * resta fuori, quindi e' coperta caso per caso, inclusi i percorsi di errore.
 */
class AutenticazioneTest extends TestCase
{
    use RefreshDatabase, CreaScenariSanitari;

    private const LOGIN = '/api/v1/auth/login';

    /* =====================================================================
     | Login: percorso felice
     * ===================================================================*/

    public function test_login_valido_restituisce_token_utente_e_redirect(): void
    {
        $user = User::factory()->create([
            'email'    => 'mario.rossi@example.it',
            'password' => 'password',
        ]);

        $response = $this->postJson(self::LOGIN, [
            'email'       => 'mario.rossi@example.it',
            'password'    => 'password',
            'device_name' => 'web',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'expires_at',
                'user' => ['id', 'name', 'email', 'role', 'status'],
                'redirect',
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'mario.rossi@example.it');

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull($response->json('expires_at'), 'Il token deve avere una scadenza esplicita.');

        // Il token esiste davvero ed e' spendibile
        $this->conToken($response->json('token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_la_password_non_viene_mai_esposta_nella_risposta(): void
    {
        User::factory()->create(['email' => 'privacy@example.it', 'password' => 'password']);

        $response = $this->postJson(self::LOGIN, [
            'email'    => 'privacy@example.it',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('user'));
    }

    public function test_la_rotta_di_atterraggio_dipende_dal_ruolo(): void
    {
        $attesi = [
            User::ROLE_ADMIN    => '/admin/panoramica',
            User::ROLE_MEDICO   => '/medico/panoramica',
            User::ROLE_PAZIENTE => '/paziente/panoramica',
        ];

        foreach ($attesi as $ruolo => $rotta) {
            $user = User::factory()->create(['role' => $ruolo, 'password' => 'password']);

            $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'password'])
                ->assertOk()
                ->assertJsonPath('redirect', $rotta);
        }
    }

    public function test_login_aggiorna_last_login_at_e_traccia_l_accesso(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->assertNull($user->last_login_at);

        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action'  => 'login',
        ]);
        $this->assertSame(1, ActivityLog::where('user_id', $user->id)->count());
    }

    /* =====================================================================
     | Login: percorsi di errore
     * ===================================================================*/

    public function test_password_errata_non_rilascia_alcun_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'sbagliata'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_email_inesistente_e_password_errata_danno_lo_stesso_messaggio(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $conEmailIgnota = $this->postJson(self::LOGIN, [
            'email' => 'nessuno@example.it', 'password' => 'password',
        ])->assertStatus(422);

        $conPasswordErrata = $this->postJson(self::LOGIN, [
            'email' => $user->email, 'password' => 'sbagliata',
        ])->assertStatus(422);

        // Enumerazione utenti: le due risposte devono essere indistinguibili
        $this->assertSame(
            $conEmailIgnota->json('errors.email'),
            $conPasswordErrata->json('errors.email'),
            'Il messaggio non deve rivelare se l\'email esiste.'
        );
    }

    public function test_un_account_sospeso_non_puo_accedere(): void
    {
        $user = User::factory()->suspended()->create(['password' => 'password']);

        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_il_ruolo_dichiarato_deve_coincidere_con_quello_reale(): void
    {
        $paziente = User::factory()->create(['password' => 'password']);

        // Il paziente prova a entrare dalla porta dell'amministrazione
        $this->postJson(self::LOGIN, [
            'email'    => $paziente->email,
            'password' => 'password',
            'role'     => User::ROLE_ADMIN,
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_il_ruolo_corretto_viene_accettato(): void
    {
        $medico = User::factory()->doctor()->create(['password' => 'password']);

        $this->postJson(self::LOGIN, [
            'email'    => $medico->email,
            'password' => 'password',
            'role'     => User::ROLE_MEDICO,
        ])->assertOk()->assertJsonPath('user.role', User::ROLE_MEDICO);
    }

    public function test_email_e_password_sono_obbligatorie(): void
    {
        $this->postJson(self::LOGIN, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_il_login_e_protetto_dal_brute_force(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $massimo = (int) config('auth.login.max_attempts');

        // Fino alla soglia si risponde "credenziali non valide" (422)
        for ($tentativo = 1; $tentativo <= $massimo; $tentativo++) {
            $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'sbagliata'])
                ->assertStatus(422);
        }

        // Superata la soglia il blocco vale anche per la password giusta
        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429)
            ->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'Troppi tentativi'));
    }

    /**
     * La regressione che ha reso rossa meta' suite E2E: con 'throttle:6,1' il
     * contatore saliva anche sugli accessi RIUSCITI, e bastavano sette login
     * legittimi di fila per bloccare un utente che non aveva sbagliato nulla.
     */
    public function test_gli_accessi_riusciti_non_fanno_scattare_il_blocco(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $massimo = (int) config('auth.login.max_attempts');

        for ($tentativo = 1; $tentativo <= $massimo + 3; $tentativo++) {
            $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'password'])
                ->assertOk();
        }
    }

    /** Un accesso riuscito azzera i tentativi falliti accumulati. */
    public function test_un_accesso_riuscito_azzera_i_tentativi_falliti(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $massimo = (int) config('auth.login.max_attempts');

        for ($tentativo = 1; $tentativo < $massimo; $tentativo++) {
            $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'sbagliata'])
                ->assertStatus(422);
        }

        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        // Contatore ripartito da zero: c'e' di nuovo spazio per sbagliare
        for ($tentativo = 1; $tentativo < $massimo; $tentativo++) {
            $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'sbagliata'])
                ->assertStatus(422);
        }
    }

    /* =====================================================================
     | Gestione dei token per dispositivo
     * ===================================================================*/

    public function test_un_nuovo_accesso_dallo_stesso_dispositivo_revoca_il_precedente(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $primo = $this->postJson(self::LOGIN, [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'web',
        ])->json('token');

        $secondo = $this->postJson(self::LOGIN, [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'web',
        ])->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->conToken($primo)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->conToken($secondo)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_dispositivi_diversi_mantengono_sessioni_indipendenti(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $web = $this->postJson(self::LOGIN, [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'web',
        ])->json('token');

        $mobile = $this->postJson(self::LOGIN, [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'mobile',
        ])->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->conToken($web)->getJson('/api/v1/auth/me')->assertOk();
        $this->conToken($mobile)->getJson('/api/v1/auth/me')->assertOk();
    }

    /* =====================================================================
     | /auth/me
     * ===================================================================*/

    public function test_me_richiede_un_token_valido(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Non autenticato.');

        $this->conToken('token-inventato')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_restituisce_il_profilo_del_paziente_con_il_medico_di_base(): void
    {
        $medico   = $this->medico(['specialization' => 'Cardiologia'], ['name' => 'Dott.ssa Anna Bianchi']);
        $paziente = $this->paziente(['primary_doctor_id' => $medico->id, 'city' => 'Bologna']);

        $this->comePaziente($paziente)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_PAZIENTE)
            ->assertJsonPath('user.patient.id', $paziente->id)
            ->assertJsonPath('user.patient.city', 'Bologna')
            ->assertJsonPath('user.patient.primary_doctor.name', 'Dott.ssa Anna Bianchi')
            ->assertJsonPath('redirect', '/paziente/panoramica');
    }

    public function test_me_restituisce_gli_orari_del_medico(): void
    {
        $medico = $this->medico([], [], conOrario: false);
        DoctorSchedule::factory()->weekday(1)->fascia('09:00:00', '13:00:00')->create(['doctor_id' => $medico->id]);
        DoctorSchedule::factory()->weekday(3)->fascia('14:00:00', '18:00:00')->create(['doctor_id' => $medico->id]);

        $this->comeMedico($medico)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_MEDICO)
            ->assertJsonPath('user.doctor.id', $medico->id)
            ->assertJsonCount(2, 'user.doctor.schedules')
            ->assertJsonPath('redirect', '/medico/panoramica');
    }

    /* =====================================================================
     | Refresh e logout
     * ===================================================================*/

    public function test_refresh_sostituisce_il_token_corrente(): void
    {
        $user    = User::factory()->create();
        $vecchio = $this->tokenDi($user, 'web');

        $nuovo = $this->conToken($vecchio)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at'])
            ->json('token');

        $this->assertNotSame($vecchio, $nuovo);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->conToken($vecchio)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->conToken($nuovo)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_logout_revoca_solo_il_dispositivo_corrente(): void
    {
        $user   = User::factory()->create();
        $web    = $this->tokenDi($user, 'web');
        $mobile = $this->tokenDi($user, 'mobile');

        $this->conToken($web)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->conToken($web)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->conToken($mobile)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_logout_all_disconnette_ogni_dispositivo(): void
    {
        $user   = User::factory()->create();
        $web    = $this->tokenDi($user, 'web');
        $mobile = $this->tokenDi($user, 'mobile');

        $this->conToken($web)->postJson('/api/v1/auth/logout-all')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->conToken($mobile)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    /* =====================================================================
     | Sospensione a sessione aperta
     * ===================================================================*/

    public function test_la_sospensione_espelle_chi_ha_gia_un_token_valido(): void
    {
        $user  = User::factory()->create();
        $token = $this->tokenDi($user);

        $this->conToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $user->update(['status' => 'sospeso']);

        $this->conToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account sospeso. Contatta l\'amministrazione.');

        // Il token viene bruciato subito: il frontend non puo' riprovare
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /* =====================================================================
     | Profilo e password
     * ===================================================================*/

    public function test_l_utente_aggiorna_i_propri_dati_di_contatto(): void
    {
        $user = User::factory()->create(['name' => 'Nome Vecchio']);

        $this->come($user)
            ->putJson('/api/v1/auth/profile', ['name' => 'Nome Nuovo', 'phone' => '3331122334'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nome Nuovo')
            ->assertJsonPath('user.phone', '3331122334');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nome Nuovo']);
    }

    public function test_l_email_deve_restare_univoca(): void
    {
        User::factory()->create(['email' => 'occupata@example.it']);
        $user = User::factory()->create();

        $this->come($user)
            ->putJson('/api/v1/auth/profile', ['email' => 'occupata@example.it'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_il_cambio_password_richiede_quella_attuale(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->come($user)
            ->putJson('/api/v1/auth/password', [
                'current_password'      => 'non-la-mia',
                'password'              => 'NuovaPassword1',
                'password_confirmation' => 'NuovaPassword1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_il_cambio_password_invalida_gli_altri_dispositivi(): void
    {
        $user   = User::factory()->create(['password' => 'password']);
        $web    = $this->tokenDi($user, 'web');
        $mobile = $this->tokenDi($user, 'mobile');

        $this->conToken($web)
            ->putJson('/api/v1/auth/password', [
                'current_password'      => 'password',
                'password'              => 'NuovaPassword1',
                'password_confirmation' => 'NuovaPassword1',
            ])
            ->assertOk();

        // Resta valido solo il dispositivo da cui e' partito il cambio
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->conToken($web)->getJson('/api/v1/auth/me')->assertOk();
        $this->conToken($mobile)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'NuovaPassword1'])->assertOk();
    }

    /* =====================================================================
     | Contratto di errore dell'API
     * ===================================================================*/

    public function test_una_rotta_api_inesistente_risponde_json_e_mai_html(): void
    {
        $this->getJson('/api/v1/rotta-che-non-esiste')
            ->assertNotFound()
            ->assertJsonPath('message', 'Endpoint non trovato.');
    }

    public function test_una_risorsa_inesistente_risponde_404_json(): void
    {
        $this->come($this->admin())
            ->getJson('/api/v1/appointments/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Risorsa non trovata.');
    }
}
