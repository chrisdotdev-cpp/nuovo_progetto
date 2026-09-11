<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppNotification;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\Prescription;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\TelemedicineService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dataset demo coerente per provare l'intero gestionale.
 * Credenziali: password "password" per tutti gli account.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding gestionale sanitario...');

        /* ------------------------- ADMIN ------------------------- */
        User::updateOrCreate(
            ['email' => 'admin@clinica.it'],
            [
                'name'     => 'Laura Bianchi',
                'password' => 'password',
                'role'     => User::ROLE_ADMIN,
                'status'   => 'attivo',
                'phone'    => '+39 06 1234567',
            ]
        );

        /* ------------------------- MEDICI ------------------------ */
        $mediciData = [
            ['Marco Ferrari',   'medico@clinica.it',   'Cardiologia',  'MI-12345', 120.00, 30],
            ['Giulia Romano',   'g.romano@clinica.it', 'Dermatologia', 'MI-23456',  90.00, 20],
            ['Andrea Costa',    'a.costa@clinica.it',  'Ortopedia',    'MI-34567', 110.00, 45],
        ];

        $medici = collect($mediciData)->map(function (array $row) {
            [$name, $email, $spec, $albo, $fee, $slot] = $row;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'     => $name,
                    'password' => 'password',
                    'role'     => User::ROLE_MEDICO,
                    'status'   => 'attivo',
                    'phone'    => '+39 3'.random_int(10000000, 99999999),
                ]
            );

            $doctor = Doctor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization'   => $spec,
                    'license_number'   => $albo,
                    'bio'              => "Specialista in {$spec} con esperienza ospedaliera e ambulatoriale.",
                    'consultation_fee' => $fee,
                    'slot_duration'    => $slot,
                    'available_online' => true,
                ]
            );

            // Orario: lunedi-venerdi, mattina e pomeriggio
            if ($doctor->schedules()->doesntExist()) {
                foreach (range(1, 5) as $weekday) {
                    $doctor->schedules()->createMany([
                        ['weekday' => $weekday, 'start_time' => '09:00', 'end_time' => '13:00'],
                        ['weekday' => $weekday, 'start_time' => '14:30', 'end_time' => '18:30'],
                    ]);
                }
            }

            return $doctor;
        });

        /* ------------------------ PAZIENTI ----------------------- */
        $pazientiData = [
            ['Marco Rossi',     'paziente@clinica.it',  'RSSMRC85M01H501Z', '1985-08-01', 'M', 'A+',  ['Penicillina'],      ['Ipertensione']],
            ['Anna Verdi',      'a.verdi@example.com',  'VRDNNA90A41F205X', '1990-01-01', 'F', '0-',  [],                   []],
            ['Luca Esposito',   'l.esposito@example.com','SPSLCU78T20F839K','1978-12-20', 'M', 'B+',  ['Lattosio'],         ['Diabete tipo 2']],
            ['Sofia Greco',     's.greco@example.com',  'GRCSFO95E55L219M', '1995-05-15', 'F', 'AB+', ['Polline', 'Acari'], ['Asma']],
        ];

        $pazienti = collect($pazientiData)->map(function (array $row, int $i) use ($medici) {
            [$name, $email, $cf, $birth, $gender, $blood, $allergie, $croniche] = $row;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'     => $name,
                    'password' => 'password',
                    'role'     => User::ROLE_PAZIENTE,
                    'status'   => 'attivo',
                    'phone'    => '+39 3'.random_int(10000000, 99999999),
                ]
            );

            return Patient::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'codice_fiscale'          => $cf,
                    'birth_date'              => $birth,
                    'gender'                  => $gender,
                    'birth_place'             => 'Milano',
                    'address'                 => 'Via Roma '.($i + 10),
                    'city'                    => 'Milano',
                    'province'                => 'MI',
                    'postal_code'             => '201'.$i.'0',
                    'blood_type'              => $blood,
                    'allergies'               => $allergie,
                    'chronic_conditions'      => $croniche,
                    'emergency_contact_name'  => 'Familiare di '.$name,
                    'emergency_contact_phone' => '+39 3'.random_int(10000000, 99999999),
                    'primary_doctor_id'       => $medici[$i % $medici->count()]->id,
                ]
            );
        });

        /* --------------------- APPUNTAMENTI ---------------------- */
        if (Appointment::doesntExist()) {
            $telemedicine = app(TelemedicineService::class);

            foreach ($pazienti as $i => $patient) {
                $doctor = $medici[$i % $medici->count()];

                // Uno passato (completato) e uno futuro (confermato)
                Appointment::create([
                    'patient_id'       => $patient->id,
                    'doctor_id'        => $doctor->id,
                    'scheduled_at'     => now()->subDays(random_int(10, 40))->setTime(10, 0),
                    'duration_minutes' => $doctor->slot_duration,
                    'type'             => 'visita',
                    'status'           => Appointment::STATUS_COMPLETATO,
                    'reason'           => 'Visita di controllo periodica',
                ]);

                $futuro = Appointment::create([
                    'patient_id'       => $patient->id,
                    'doctor_id'        => $doctor->id,
                    'scheduled_at'     => now()->addDays($i + 1)->setTime(9 + $i, 30),
                    'duration_minutes' => $doctor->slot_duration,
                    'type'             => $i % 2 === 0 ? 'visita' : 'telemedicina',
                    'status'           => Appointment::STATUS_CONFERMATO,
                    'reason'           => 'Controllo programmato',
                    'confirmed_at'     => now(),
                ]);

                if ($futuro->type === 'telemedicina') {
                    $telemedicine->createSessionFor($futuro);
                }
            }
        }

        /* ------------------- CARTELLA CLINICA -------------------- */
        if (MedicalRecord::doesntExist()) {
            foreach ($pazienti as $i => $patient) {
                $doctorId = $medici[$i % $medici->count()]->id;

                MedicalRecord::create([
                    'patient_id'  => $patient->id,
                    'doctor_id'   => $doctorId,
                    'type'        => 'referto',
                    'title'       => 'Elettrocardiogramma a riposo',
                    'description' => 'Ritmo sinusale regolare, nessuna alterazione significativa.',
                    'vitals'      => ['pressione' => '125/80', 'frequenza' => 72, 'peso' => 70 + $i],
                    'recorded_at' => now()->subDays(random_int(10, 40)),
                ]);

                MedicalRecord::create([
                    'patient_id'  => $patient->id,
                    'doctor_id'   => $doctorId,
                    'type'        => 'diagnosi',
                    'title'       => 'Controllo periodico',
                    'description' => 'Quadro clinico stabile, si conferma terapia in corso.',
                    'icd10_code'  => 'Z00.0',
                    'recorded_at' => now()->subDays(random_int(1, 9)),
                ]);
            }
        }

        /* ------------------------ FARMACI ------------------------ */
        if (Medicine::doesntExist()) {
            $farmaci = [
                ['Tachipirina 500mg', 'Paracetamolo',  'compresse', '500mg',  4.50, false, 240, 50, '2027-06-30'],
                ['Augmentin 1g',      'Amoxicillina',  'compresse', '1g',    12.80, true,   45, 30, '2026-11-15'],
                ['Cardioaspirin',     'Ac. Acetilsalicilico', 'compresse', '100mg', 6.20, false, 18, 25, '2027-01-31'],
                ['Enapren 20mg',      'Enalapril',     'compresse', '20mg',   9.90, true,  120, 40, '2026-09-30'],
                ['Ventolin spray',    'Salbutamolo',   'aerosol',   '100mcg', 8.40, true,    6, 15, '2026-08-20'],
                ['Voltaren gel',      'Diclofenac',    'gel',       '1%',     9.30, false,  85, 20, '2028-03-31'],
            ];

            foreach ($farmaci as $f) {
                Medicine::create([
                    'name'                  => $f[0],
                    'active_ingredient'     => $f[1],
                    'aic_code'              => (string) random_int(100000000, 999999999),
                    'form'                  => $f[2],
                    'dosage'                => $f[3],
                    'manufacturer'          => 'Farmaceutica Italia S.p.A.',
                    'price'                 => $f[4],
                    'requires_prescription' => $f[5],
                    'stock_quantity'        => $f[6],
                    'min_stock'             => $f[7],
                    'batch'                 => 'LOT-'.strtoupper(Str::random(6)),
                    'expiry_date'           => $f[8],
                ]);
            }
        }

        /* --------------------- PRESCRIZIONI ---------------------- */
        if (Prescription::doesntExist()) {
            foreach ($pazienti->take(3) as $i => $patient) {
                $prescription = Prescription::create([
                    'code'        => sprintf('RX-%d-%06d', now()->year, $i + 1),
                    'patient_id'  => $patient->id,
                    'doctor_id'   => $medici[$i % $medici->count()]->id,
                    'status'      => 'attiva',
                    'issued_at'   => now()->subDays(random_int(1, 20))->toDateString(),
                    'valid_until' => now()->addMonths(6)->toDateString(),
                    'notes'       => 'Assumere a stomaco pieno.',
                ]);

                $prescription->items()->createMany([
                    [
                        'medicine_id'   => Medicine::where('name', 'like', 'Tachipirina%')->value('id'),
                        'name'          => 'Tachipirina 500mg',
                        'dosage'        => '1 compressa',
                        'frequency'     => '2 volte al giorno',
                        'duration_days' => 7,
                        'quantity'      => 14,
                    ],
                    [
                        'name'          => 'Integratore vitamina D',
                        'dosage'        => '10 gocce',
                        'frequency'     => '1 volta al giorno',
                        'duration_days' => 30,
                        'quantity'      => 1,
                    ],
                ]);
            }
        }

        /* ---------------------- RICHIESTE ------------------------ */
        if (PatientRequest::doesntExist()) {
            PatientRequest::create([
                'patient_id'  => $pazienti[0]->id,
                'doctor_id'   => $medici[0]->id,
                'subject'     => 'Dolore toracico dopo sforzo',
                'description' => 'Da tre giorni avverto un dolore al petto durante le salite. Allego ultimo ECG.',
                'priority'    => 'alta',
                'status'      => 'aperta',
            ]);

            PatientRequest::create([
                'patient_id'  => $pazienti[1]->id,
                'doctor_id'   => null, // coda generale
                'subject'     => 'Rinnovo ricetta cronica',
                'description' => 'Vorrei rinnovare la prescrizione della terapia che assumo abitualmente.',
                'priority'    => 'bassa',
                'status'      => 'aperta',
            ]);

            PatientRequest::create([
                'patient_id'   => $pazienti[2]->id,
                'doctor_id'    => $medici[2]->id,
                'subject'      => 'Esito radiografia ginocchio',
                'description'  => 'Ho eseguito la radiografia richiesta, come devo procedere?',
                'priority'     => 'media',
                'status'       => 'risposta',
                'response'     => 'Il referto e\' nella norma. Prosegua con la fisioterapia per altre due settimane.',
                'responded_at' => now()->subDay(),
            ]);
        }

        /* ---------------------- FATTURE -------------------------- */
        if (Invoice::doesntExist()) {
            $invoiceService = app(InvoiceService::class);

            foreach ($pazienti as $i => $patient) {
                $invoice = $invoiceService->create([
                    'patient_id' => $patient->id,
                    'issue_date' => now()->subDays(random_int(5, 60))->toDateString(),
                    'tax_rate'   => 0, // prestazioni sanitarie esenti IVA
                    'items'      => [
                        ['description' => 'Visita specialistica', 'quantity' => 1, 'unit_price' => 120.00],
                        ['description' => 'Elettrocardiogramma',  'quantity' => 1, 'unit_price' => 45.00],
                    ],
                ]);

                // Meta' delle fatture risultano incassate
                if ($i % 2 === 0) {
                    $invoiceService->registerPayment($invoice, [
                        'amount' => $invoice->total,
                        'method' => 'carta',
                    ]);
                }
            }
        }

        /* ---------------------- NOTIFICHE ------------------------ */
        if (AppNotification::doesntExist()) {
            User::all()->each(function (User $user) {
                AppNotification::create([
                    'user_id'  => $user->id,
                    'category' => 'sistema',
                    'level'    => 'info',
                    'title'    => 'Benvenuto nel gestionale',
                    'body'     => 'Il tuo account e\' attivo. Completa il profilo dalle impostazioni.',
                    'link'     => "/{$user->role}/impostazioni",
                ]);
            });
        }

        $this->command->info('Seeding completato.');
        $this->command->table(
            ['Ruolo', 'Email', 'Password'],
            [
                ['admin',    'admin@clinica.it',    'password'],
                ['medico',   'medico@clinica.it',   'password'],
                ['paziente', 'paziente@clinica.it', 'password'],
            ]
        );
    }
}
