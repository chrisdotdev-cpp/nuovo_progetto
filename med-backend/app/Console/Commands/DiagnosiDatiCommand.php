<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostica lo stato dei dati applicativi.
 *
 * Risponde in dieci secondi alla domanda "perche' il paziente non vede medici?"
 * senza aprire phpMyAdmin: distingue un problema di schema (tabella mancante)
 * da un problema di dati (tabella vuota o profili scollegati).
 *
 *   php artisan med:diagnosi
 *   php artisan med:diagnosi --fix    esegue anche SincronizzaProfiliSeeder
 */
class DiagnosiDatiCommand extends Command
{
    protected $signature = 'med:diagnosi {--fix : Crea i profili mancanti eseguendo SincronizzaProfiliSeeder}';

    protected $description = 'Verifica lo stato di users/doctors/patients e segnala cosa impedisce la prenotazione';

    /** Problemi bloccanti raccolti durante i controlli. */
    private array $problemi = [];

    public function handle(): int
    {
        $this->info('=== DIAGNOSI DATI - Gestionale Sanitario ===');
        $this->newLine();

        if (! $this->verificaSchema()) {
            return self::FAILURE;
        }

        $this->verificaProfili();
        $this->verificaOrari();
        $this->verificaOrfani();
        $this->riepilogoAppuntamenti();

        return $this->esito();
    }

    /* ------------------------------------------------------------------ */

    /** Le tabelle esistono? Se no il problema e' a monte: mancano le migration. */
    private function verificaSchema(): bool
    {
        $attese  = ['users', 'doctors', 'patients', 'doctor_schedules', 'appointments'];
        $mancanti = array_values(array_filter($attese, fn ($t) => ! Schema::hasTable($t)));

        if ($mancanti !== []) {
            $this->error('Tabelle mancanti: '.implode(', ', $mancanti));
            $this->line('  Esegui:  php artisan migrate');

            return false;
        }

        $this->line('<info>[OK]</info> Schema: tutte le tabelle richieste esistono.');

        // primary_doctor_id e' la colonna che collega paziente e medico di base
        if (! Schema::hasColumn('patients', 'primary_doctor_id')) {
            $this->error('  La colonna patients.primary_doctor_id non esiste. Esegui: php artisan migrate');

            return false;
        }

        $this->line('<info>[OK]</info> Colonna patients.primary_doctor_id presente.');
        $this->newLine();

        return true;
    }

    /** Il cuore della diagnosi: quanti account hanno davvero un profilo. */
    private function verificaProfili(): void
    {
        $mediciUser   = User::where('role', User::ROLE_MEDICO)->count();
        $pazientiUser = User::where('role', User::ROLE_PAZIENTE)->count();
        $doctors      = Doctor::count();
        $patients     = Patient::count();

        $this->table(
            ['Ruolo in users', 'Account', 'Profili collegati', 'Stato'],
            [
                ['medico',   $mediciUser,   $doctors,  $this->stato($mediciUser, $doctors)],
                ['paziente', $pazientiUser, $patients, $this->stato($pazientiUser, $patients)],
            ]
        );

        if ($doctors === 0 && $mediciUser > 0) {
            $this->problemi[] = 'La tabella `doctors` e\' vuota: GET /api/v1/doctors restituisce una lista vuota '
                .'e il paziente non vede nessun medico da selezionare.';
        }

        if ($patients === 0 && $pazientiUser > 0) {
            $this->problemi[] = 'La tabella `patients` e\' vuota: la prenotazione fallisce con '
                .'422 "Profilo paziente non trovato per questo account".';
        }

        // Elenco puntuale degli account scoperti: piu' utile di un semplice conteggio
        $mediciSenza = User::where('role', User::ROLE_MEDICO)
            ->whereDoesntHave('doctor')->pluck('email');

        if ($mediciSenza->isNotEmpty()) {
            $this->warn('Medici senza profilo in `doctors`: '.$mediciSenza->implode(', '));
            $this->problemi[] = "{$mediciSenza->count()} account medico non hanno un record in `doctors`.";
        }

        $pazientiSenza = User::where('role', User::ROLE_PAZIENTE)
            ->whereDoesntHave('patient')->pluck('email');

        if ($pazientiSenza->isNotEmpty()) {
            $this->warn('Pazienti senza profilo in `patients`: '.$pazientiSenza->implode(', '));
            $this->problemi[] = "{$pazientiSenza->count()} account paziente non hanno un record in `patients`.";
        }

        $senzaMedicoBase = Patient::whereNull('primary_doctor_id')->count();

        if ($senzaMedicoBase > 0) {
            $this->warn("Pazienti senza medico di base (primary_doctor_id NULL): {$senzaMedicoBase}");
        }

        $this->newLine();
    }

    /**
     * Un medico senza fasce orarie attive esiste ma non e' prenotabile:
     * availableSlots() restituisce [] e il calendario resta vuoto.
     */
    private function verificaOrari(): void
    {
        $senzaOrario = Doctor::whereDoesntHave('schedules', fn ($q) => $q->where('active', true))
            ->with('user')
            ->get();

        if ($senzaOrario->isEmpty()) {
            $this->line('<info>[OK]</info> Tutti i medici hanno almeno una fascia oraria attiva. '
                .'(totale fasce: '.DoctorSchedule::count().')');
            $this->newLine();

            return;
        }

        $this->warn('Medici visibili in lista ma NON prenotabili (nessun orario attivo):');

        foreach ($senzaOrario as $doctor) {
            $this->line('  - '.($doctor->user?->name ?? "doctor #{$doctor->id}"));
        }

        $this->problemi[] = "{$senzaOrario->count()} medici non hanno orari: il paziente li vede ma non trova slot.";
        $this->newLine();
    }

    /**
     * Profili il cui `user` e' stato eliminato (anche soft delete).
     * Sono la causa tipica di nomi vuoti o errori 500 nella serializzazione.
     */
    private function verificaOrfani(): void
    {
        $doctorOrfani  = Doctor::whereDoesntHave('user')->count();
        $patientOrfani = Patient::whereDoesntHave('user')->count();

        if ($doctorOrfani === 0 && $patientOrfani === 0) {
            $this->line('<info>[OK]</info> Nessun profilo orfano: ogni doctor/patient punta a un utente valido.');
            $this->newLine();

            return;
        }

        if ($doctorOrfani > 0) {
            $this->warn("Record in `doctors` con utente mancante o cancellato: {$doctorOrfani}");
            $this->problemi[] = "{$doctorOrfani} medici orfani: vengono esclusi dalla lista (vedi DoctorController::index).";
        }

        if ($patientOrfani > 0) {
            $this->warn("Record in `patients` con utente mancante o cancellato: {$patientOrfani}");
        }

        $this->newLine();
    }

    private function riepilogoAppuntamenti(): void
    {
        $totale = Appointment::count();

        $this->line("Appuntamenti in archivio: {$totale}");

        if ($totale > 0) {
            $righe = Appointment::selectRaw('status, COUNT(*) as n')
                ->groupBy('status')
                ->pluck('n', 'status')
                ->map(fn ($n, $s) => [$s, $n])
                ->values()
                ->all();

            $this->table(['Stato', 'Numero'], $righe);
        }

        $this->newLine();
    }

    private function esito(): int
    {
        if ($this->problemi === []) {
            $this->info('Nessun problema rilevato: i dati sono coerenti.');

            return self::SUCCESS;
        }

        $this->error('PROBLEMI RILEVATI');

        foreach ($this->problemi as $i => $problema) {
            $this->line('  '.($i + 1).'. '.$problema);
        }

        $this->newLine();

        if ($this->option('fix')) {
            $this->info('Correzione in corso (--fix)...');
            $this->call('db:seed', ['--class' => 'SincronizzaProfiliSeeder', '--force' => true]);
            $this->newLine();
            $this->info('Fatto. Rilancia `php artisan med:diagnosi` per confermare.');

            return self::SUCCESS;
        }

        $this->line('Soluzione:  php artisan med:diagnosi --fix');
        $this->line('    oppure:  php artisan db:seed --class=SincronizzaProfiliSeeder');

        return self::FAILURE;
    }

    /** Etichetta sintetica del confronto account/profili. */
    private function stato(int $account, int $profili): string
    {
        if ($account === 0) {
            return 'nessun account';
        }

        return $profili >= $account ? 'OK' : 'INCOMPLETO';
    }
}
