<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crea (o ripristina) i tre account demo necessari a usare l'applicazione.
 *
 * Serve quando non si vuole rigenerare l'intero dataset con
 * `php artisan migrate:fresh --seed`: qui si tocca solo la tabella users
 * (piu' i profili doctors/patients collegati), lasciando intatto il resto.
 *
 *   php artisan users:demo
 *   php artisan users:demo --password=Segreta123
 *   php artisan users:demo --reset      # reimposta anche la password degli account esistenti
 */
class CreateDemoUsers extends Command
{
    protected $signature = 'users:demo
                            {--password=password : Password assegnata agli account demo}
                            {--reset : Reimposta password e stato anche sugli account gia\' esistenti}';

    protected $description = 'Crea gli utenti demo (admin, medico, paziente) con ruolo e stato corretti';

    public function handle(): int
    {
        $password = (string) $this->option('password');
        $reset    = (bool) $this->option('reset');

        /*
         * Il cast 'password' => 'hashed' sul model User applica bcrypt in automatico:
         * qui si passa la password in chiaro, NON gia' hashata, altrimenti verrebbe
         * hashata due volte e il login fallirebbe.
         */
        $definizioni = [
            [
                'email' => 'admin@clinica.it',
                'name'  => 'Laura Bianchi',
                'role'  => User::ROLE_ADMIN,
                'phone' => '+39 06 1234567',
            ],
            [
                'email' => 'medico@clinica.it',
                'name'  => 'Marco Ferrari',
                'role'  => User::ROLE_MEDICO,
                'phone' => '+39 340 1112223',
            ],
            [
                'email' => 'paziente@clinica.it',
                'name'  => 'Marco Rossi',
                'role'  => User::ROLE_PAZIENTE,
                'phone' => '+39 340 4445556',
            ],
        ];

        $righe = [];

        foreach ($definizioni as $definizione) {
            $utente = User::withTrashed()->firstWhere('email', $definizione['email']);
            $nuovo  = $utente === null;

            if ($nuovo) {
                $utente = new User(['email' => $definizione['email']]);
            }

            // Un account soft-deleted non riuscirebbe ad autenticarsi: si ripristina
            if ($utente->trashed()) {
                $utente->restore();
            }

            $utente->fill([
                'name'   => $utente->name ?: $definizione['name'],
                'email'  => $definizione['email'],
                'role'   => $definizione['role'],
                'status' => 'attivo',   // isActive() controlla esattamente questo valore
                'phone'  => $utente->phone ?: $definizione['phone'],
            ]);

            if ($nuovo || $reset) {
                $utente->password = $password;
            }

            $utente->save();

            // Il profilo collegato deve esistere: senza, /auth/me e le dashboard restano vuote
            $this->assicuraProfilo($utente);

            $righe[] = [
                $definizione['email'],
                $definizione['role'],
                'attivo',
                $nuovo ? 'creato' : ($reset ? 'aggiornato + password' : 'gia\' presente'),
            ];
        }

        $this->newLine();
        $this->table(['Email', 'Ruolo', 'Stato', 'Esito'], $righe);

        $this->info("Password degli account creati/reimpostati: {$password}");
        $this->line('Per reimpostare anche gli account esistenti: php artisan users:demo --reset');

        return self::SUCCESS;
    }

    /**
     * Il ruolo da solo non basta: un medico senza record in `doctors` non puo'
     * avere agenda ne' prescrizioni, un paziente senza record in `patients`
     * non puo' prenotare. Si crea il profilo minimo mancante.
     */
    private function assicuraProfilo(User $utente): void
    {
        if ($utente->isDoctor() && ! $utente->doctor) {
            Doctor::create([
                'user_id'          => $utente->id,
                'specialization'   => 'Medicina Generale',
                'license_number'   => 'DEMO-'.$utente->id,
                'consultation_fee' => 80.00,
                'slot_duration'    => 30,
                'available_online' => true,
            ]);

            $this->line("  Profilo medico creato per {$utente->email}");
        }

        if ($utente->isPatient() && ! $utente->patient) {
            Patient::create([
                'user_id'        => $utente->id,
                'codice_fiscale' => 'DEMO'.str_pad((string) $utente->id, 12, '0', STR_PAD_LEFT),
                'birth_date'     => '1985-08-01',
                'gender'         => 'M',
                'city'           => 'Milano',
                'province'       => 'MI',
            ]);

            $this->line("  Profilo paziente creato per {$utente->email}");
        }
    }
}
