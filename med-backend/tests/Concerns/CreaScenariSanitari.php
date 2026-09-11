<?php

namespace Tests\Concerns;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Scorciatoie condivise dai test.
 *
 * Il dominio ha una regola ricorrente: il ruolo sta in `users`, il profilo in
 * `doctors` / `patients`, e quasi ogni endpoint richiede che i due siano
 * allineati. Questi helper costruiscono lo scenario coerente in una riga sola,
 * cosi' i test restano leggibili e parlano di cliniche, non di factory.
 */
trait CreaScenariSanitari
{
    /* ---------------------------------------------------------------------
     | Attori
     * -------------------------------------------------------------------*/

    protected function admin(array $attributi = []): User
    {
        return User::factory()->admin()->create($attributi);
    }

    /**
     * Medico completo: account con ruolo 'medico' + profilo in `doctors`.
     * Di default ha gia' l'orario settimanale, altrimenti non sarebbe prenotabile.
     */
    protected function medico(array $profilo = [], array $account = [], bool $conOrario = true): Doctor
    {
        $doctor = Doctor::factory()->create([
            'user_id' => User::factory()->doctor()->create($account)->id,
            ...$profilo,
        ]);

        return $conOrario ? $this->conOrarioSettimanale($doctor) : $doctor;
    }

    /** Paziente completo: account con ruolo 'paziente' + anagrafica in `patients`. */
    protected function paziente(array $profilo = [], array $account = []): Patient
    {
        return Patient::factory()->create([
            'user_id' => User::factory()->create($account)->id,
            ...$profilo,
        ]);
    }

    /**
     * Account con ruolo 'paziente' ma senza record in `patients`.
     * E' il disallineamento che in produzione generava i 500 su /patients/me.
     */
    protected function pazienteSenzaProfilo(array $account = []): User
    {
        return User::factory()->create($account);
    }

    /* ---------------------------------------------------------------------
     | Agenda
     * -------------------------------------------------------------------*/

    /** Orario aperto tutti i giorni: rende prenotabile qualsiasi data futura. */
    protected function conOrarioSettimanale(Doctor $doctor, string $dalle = '08:00:00', string $alle = '20:00:00'): Doctor
    {
        foreach (range(1, 7) as $giorno) {
            DoctorSchedule::factory()->weekday($giorno)->fascia($dalle, $alle)->create([
                'doctor_id' => $doctor->id,
            ]);
        }

        return $doctor->fresh();
    }

    /**
     * Un orario sicuramente futuro e dentro l'agenda standard.
     * Usare sempre questo al posto di date scritte a mano: evita i falsi
     * fallimenti quando la suite gira a cavallo della mezzanotte.
     */
    protected function slotFuturo(int $ora = 10, int $minuti = 0, int $traGiorni = 1): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays($traGiorni)->setTime($ora, $minuti, 0);
    }

    /* ---------------------------------------------------------------------
     | Autenticazione
     * -------------------------------------------------------------------*/

    /**
     * Token Sanctum reale (non TransientToken): serve perche' logout, refresh
     * e il middleware 'active' agiscono sul PersonalAccessToken corrente.
     */
    protected function tokenDi(User $user, string $device = 'web'): string
    {
        return $user->createToken($device, ['*'], now()->addDay())->plainTextToken;
    }

    /**
     * Imposta il Bearer token azzerando prima la guard.
     *
     * PERCHE' NON BASTA withToken()
     * -----------------------------
     * RequestGuard memorizza l'utente risolto:
     *
     *   // vendor/laravel/framework/.../RequestGuard.php
     *   if (! is_null($this->user)) { return $this->user; }
     *
     * In un test Feature l'applicazione e' la stessa per tutte le richieste del
     * metodo, quindi il primo accesso andato a buon fine "congela" l'utente:
     * ogni chiamata successiva viene servita con quell'identita' anche se il
     * token e' cambiato, e' stato revocato o l'account e' stato sospeso nel
     * frattempo. L'effetto e' subdolo: i test sui permessi passano in verde
     * perche' interrogano sempre lo stesso utente, e i test su logout/refresh
     * falliscono perche' il token revocato sembra ancora valido.
     *
     * Il null invece non viene memorizzato, ed e' il motivo per cui alcuni casi
     * sembravano funzionare: un token gia' invalido veniva rivalutato ogni volta.
     *
     * forgetGuards() scarta le guard risolte e obbliga la richiesta successiva a
     * ripartire dal token e dal database.
     */
    protected function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /** Autentica le richieste successive come questo utente. */
    protected function come(User $user, string $device = 'web'): static
    {
        return $this->conToken($this->tokenDi($user, $device));
    }

    /** Come come(), ma partendo dal profilo invece che dall'account. */
    protected function comeMedico(Doctor $doctor): static
    {
        return $this->come($doctor->user);
    }

    protected function comePaziente(Patient $patient): static
    {
        return $this->come($patient->user);
    }
}
