<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Allinea i profili applicativi agli account gia' presenti in `users`.
 *
 * PERCHE' ESISTE
 * --------------
 * `users` contiene solo l'account (autenticazione + ruolo). Il profilo di dominio
 * vive in `doctors` e `patients`. Se si popola `users` a mano - via phpMyAdmin,
 * import SQL parziale, registrazioni manuali - le due tabelle di profilo restano
 * vuote e l'applicazione si comporta cosi':
 *
 *   - GET /api/v1/doctors  ->  200 con `data: []`  ->  il paziente non vede medici
 *   - POST /api/v1/appointments  ->  422 "Profilo paziente non trovato"
 *   - il medico non vede assistiti, l'admin non vede prenotazioni
 *
 * Nessun errore in console: la lista e' semplicemente vuota. Questo seeder chiude
 * il buco creando i profili mancanti.
 *
 * IDEMPOTENTE
 * -----------
 * Si puo' rilanciare quante volte si vuole: crea solo cio' che manca, non
 * duplica e non sovrascrive dati esistenti (bio, tariffe, anagrafiche gia'
 * compilate restano intatte).
 *
 * USO
 *   php artisan db:seed --class=SincronizzaProfiliSeeder
 */
class SincronizzaProfiliSeeder extends Seeder
{
    /**
     * Orario settimanale di default: lunedi'-venerdi', mattina e pomeriggio.
     * Senza almeno una fascia attiva AppointmentService::availableSlots()
     * restituisce [] e il calendario di prenotazione resta vuoto.
     *
     * @var array<int, array{0:int,1:string,2:string}>
     */
    private const ORARIO_DEFAULT = [
        [1, '09:00:00', '13:00:00'], [1, '14:00:00', '18:00:00'],
        [2, '09:00:00', '13:00:00'], [2, '14:00:00', '18:00:00'],
        [3, '09:00:00', '13:00:00'], [3, '14:00:00', '18:00:00'],
        [4, '09:00:00', '13:00:00'], [4, '14:00:00', '18:00:00'],
        [5, '09:00:00', '13:00:00'],
    ];

    /**
     * Specializzazioni assegnate a rotazione ai medici senza profilo.
     * Sono un valore di partenza modificabile dall'admin: la colonna e'
     * obbligatoria (string 120, NOT NULL) quindi non puo' restare vuota.
     *
     * @var array<int, string>
     */
    private const SPECIALIZZAZIONI = [
        'Medicina Generale',
        'Cardiologia',
        'Dermatologia',
        'Ortopedia',
        'Ginecologia',
        'Pediatria',
        'Neurologia',
        'Oculistica',
    ];

    public function run(): void
    {
        $this->command->info('Sincronizzazione profili medici e pazienti...');

        $medici   = $this->sincronizzaMedici();
        $orari    = $this->sincronizzaOrari();
        $pazienti = $this->sincronizzaPazienti();
        $assegn   = $this->assegnaMediciDiBase();

        $this->command->newLine();
        $this->command->table(
            ['Operazione', 'Record'],
            [
                ['Profili medico creati',        $medici],
                ['Orari settimanali creati',     $orari],
                ['Profili paziente creati',      $pazienti],
                ['Medici di base assegnati',     $assegn],
            ]
        );

        $this->command->newLine();
        $this->command->info(sprintf(
            'Stato finale: %d medici, %d pazienti, %d fasce orarie.',
            Doctor::count(),
            Patient::count(),
            DoctorSchedule::count()
        ));
    }

    /* ---------------------------------------------------------------------
     | 1. Un profilo Doctor per ogni user con ruolo "medico"
     * -------------------------------------------------------------------*/
    private function sincronizzaMedici(): int
    {
        // whereDoesntHave: si toccano solo gli account ancora senza profilo,
        // cosi' i medici gia' configurati non vengono sfiorati.
        $senzaProfilo = User::query()
            ->where('role', User::ROLE_MEDICO)
            ->whereDoesntHave('doctor')
            ->orderBy('id')
            ->get();

        if ($senzaProfilo->isEmpty()) {
            return 0;
        }

        // Punto di partenza della rotazione: continua da dove si era arrivati
        $offset = Doctor::count();

        foreach ($senzaProfilo as $indice => $user) {
            Doctor::create([
                'user_id'          => $user->id,
                'specialization'   => self::SPECIALIZZAZIONI[($offset + $indice) % count(self::SPECIALIZZAZIONI)],
                'license_number'   => $this->numeroAlboLibero($user->id),
                'bio'              => "Profilo generato automaticamente per {$user->name}. Modificabile dall'area medico.",
                'consultation_fee' => 80.00,
                'slot_duration'    => 30,
                'available_online' => true,
            ]);
        }

        return $senzaProfilo->count();
    }

    /**
     * `license_number` ha un indice UNIQUE: se il numero derivato dall'id e'
     * gia' occupato (import precedenti, dati demo) si scala finche' e' libero,
     * invece di far fallire l'intero seeder con una QueryException.
     */
    private function numeroAlboLibero(int $userId): string
    {
        $progressivo = $userId;

        do {
            $numero = sprintf('AUTO-%05d', $progressivo);
            $progressivo++;
        } while (Doctor::withTrashed()->where('license_number', $numero)->exists());

        return $numero;
    }

    /* ---------------------------------------------------------------------
     | 2. Orario settimanale per i medici che non ne hanno
     * -------------------------------------------------------------------*/
    private function sincronizzaOrari(): int
    {
        $senzaOrario = Doctor::query()->whereDoesntHave('schedules')->get();
        $creati      = 0;

        foreach ($senzaOrario as $doctor) {
            foreach (self::ORARIO_DEFAULT as [$weekday, $inizio, $fine]) {
                // firstOrCreate rispetta l'indice unique (doctor_id, weekday, start_time)
                DoctorSchedule::firstOrCreate(
                    ['doctor_id' => $doctor->id, 'weekday' => $weekday, 'start_time' => $inizio],
                    ['end_time' => $fine, 'active' => true]
                );
                $creati++;
            }
        }

        return $creati;
    }

    /* ---------------------------------------------------------------------
     | 3. Un profilo Patient per ogni user con ruolo "paziente"
     * -------------------------------------------------------------------*/
    private function sincronizzaPazienti(): int
    {
        $senzaProfilo = User::query()
            ->where('role', User::ROLE_PAZIENTE)
            ->whereDoesntHave('patient')
            ->orderBy('id')
            ->get();

        foreach ($senzaProfilo as $user) {
            Patient::create([
                'user_id' => $user->id,
                // codice_fiscale e' UNIQUE: si lascia NULL invece di inventare un
                // valore fittizio che bloccherebbe il paziente al primo salvataggio reale.
                'codice_fiscale'     => null,
                'birth_date'         => null,
                'gender'             => null,
                'allergies'          => [],
                'chronic_conditions' => [],
                'notes'              => 'Anagrafica da completare.',
            ]);
        }

        return $senzaProfilo->count();
    }

    /* ---------------------------------------------------------------------
     | 4. Medico di base a rotazione per i pazienti che ne sono privi
     * -------------------------------------------------------------------*/
    private function assegnaMediciDiBase(): int
    {
        $mediciIds = Doctor::query()->orderBy('id')->pluck('id');

        if ($mediciIds->isEmpty()) {
            $this->command->warn('Nessun medico in archivio: primary_doctor_id non assegnato.');

            return 0;
        }

        $daAssegnare = Patient::query()
            ->whereNull('primary_doctor_id')
            ->orderBy('id')
            ->pluck('id');

        if ($daAssegnare->isEmpty()) {
            return 0;
        }

        // Round-robin: distribuisce il carico invece di appiccicare tutti al primo medico
        DB::transaction(function () use ($daAssegnare, $mediciIds) {
            foreach ($daAssegnare as $indice => $patientId) {
                Patient::whereKey($patientId)->update([
                    'primary_doctor_id' => $mediciIds[$indice % $mediciIds->count()],
                ]);
            }
        });

        return $daAssegnare->count();
    }
}
