<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorAbsence;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaScenariSanitari;
use Tests\TestCase;

/**
 * Anagrafiche: profilo paziente e profilo medico.
 *
 * Sono le due tabelle che il resto dell'applicazione da' per scontate. Qui si
 * verifica chi puo' crearle, chi puo' leggerle e - soprattutto - che l'elenco
 * medici non venga mai svuotato da un record incoerente, perche' senza elenco
 * il paziente non ha modo di prenotare.
 */
class ProfiliTest extends TestCase
{
    use RefreshDatabase, CreaScenariSanitari;

    /* =====================================================================
     | Profilo paziente
     * ===================================================================*/

    public function test_l_admin_crea_un_profilo_paziente(): void
    {
        $account = User::factory()->create();

        $this->come($this->admin())
            ->postJson('/api/v1/patients', [
                'user_id'                 => $account->id,
                'codice_fiscale'          => 'VRDLCU85M12F205Z',
                'birth_date'              => '1985-08-12',
                'gender'                  => 'M',
                'city'                    => 'Milano',
                'province'                => 'MI',
                'blood_type'              => 'A+',
                'allergies'               => ['Penicillina', 'Lattosio'],
                'chronic_conditions'      => ['Ipertensione'],
                'emergency_contact_name'  => 'Anna Verdi',
                'emergency_contact_phone' => '3401122334',
            ])
            ->assertCreated()
            ->assertJsonPath('data.codice_fiscale', 'VRDLCU85M12F205Z')
            ->assertJsonPath('data.city', 'Milano');

        $paziente = Patient::where('user_id', $account->id)->first();

        $this->assertNotNull($paziente);
        $this->assertSame(['Penicillina', 'Lattosio'], $paziente->allergies, 'Le allergie sono un cast array.');
    }

    public function test_lo_stesso_account_non_puo_avere_due_anagrafiche(): void
    {
        $esistente = $this->paziente();

        $this->come($this->admin())
            ->postJson('/api/v1/patients', ['user_id' => $esistente->user_id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_il_codice_fiscale_deve_essere_univoco_e_di_16_caratteri(): void
    {
        $this->paziente(['codice_fiscale' => 'RSSMRA80A01H501U']);
        $account = User::factory()->create();

        $this->come($this->admin())
            ->postJson('/api/v1/patients', [
                'user_id'        => $account->id,
                'codice_fiscale' => 'RSSMRA80A01H501U',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('codice_fiscale');

        $this->come($this->admin())
            ->postJson('/api/v1/patients', ['user_id' => $account->id, 'codice_fiscale' => 'TROPPOCORTO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('codice_fiscale');
    }

    public function test_ne_medico_ne_paziente_possono_creare_anagrafiche(): void
    {
        $account = User::factory()->create();

        $this->comeMedico($this->medico())
            ->postJson('/api/v1/patients', ['user_id' => $account->id])
            ->assertForbidden();

        $this->comePaziente($this->paziente())
            ->postJson('/api/v1/patients', ['user_id' => $account->id])
            ->assertForbidden();
    }

    public function test_un_paziente_non_puo_aprire_la_scheda_di_un_altro(): void
    {
        $io    = $this->paziente();
        $altro = $this->paziente();

        $this->comePaziente($io)->getJson("/api/v1/patients/{$io->id}")->assertOk();
        $this->comePaziente($io)->getJson("/api/v1/patients/{$altro->id}")->assertForbidden();
    }

    public function test_il_medico_apre_la_scheda_solo_dei_pazienti_che_segue(): void
    {
        $medico    = $this->medico();
        $assistito = $this->paziente(['primary_doctor_id' => $medico->id]);
        $estraneo  = $this->paziente();

        $this->comeMedico($medico)->getJson("/api/v1/patients/{$assistito->id}")->assertOk();
        $this->comeMedico($medico)->getJson("/api/v1/patients/{$estraneo->id}")->assertForbidden();

        // Basta un appuntamento perche' il paziente entri nel perimetro del medico
        Appointment::factory()->create(['patient_id' => $estraneo->id, 'doctor_id' => $medico->id]);

        $this->comeMedico($medico)->getJson("/api/v1/patients/{$estraneo->id}")->assertOk();
    }

    public function test_il_paziente_aggiorna_la_propria_scheda_ma_non_si_assegna_il_medico_di_base(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente(['city' => 'Torino']);

        $this->comePaziente($paziente)
            ->putJson("/api/v1/patients/{$paziente->id}", [
                'city'              => 'Genova',
                'primary_doctor_id' => $medico->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.city', 'Genova');

        $aggiornato = $paziente->fresh();

        $this->assertSame('Genova', $aggiornato->city);
        $this->assertNull(
            $aggiornato->primary_doctor_id,
            'Il medico di base lo assegna la struttura, non il paziente.'
        );
    }

    public function test_l_admin_puo_assegnare_il_medico_di_base(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $this->come($this->admin())
            ->putJson("/api/v1/patients/{$paziente->id}", ['primary_doctor_id' => $medico->id])
            ->assertOk();

        $this->assertSame($medico->id, $paziente->fresh()->primary_doctor_id);
    }

    public function test_l_eta_viene_calcolata_dalla_data_di_nascita(): void
    {
        $paziente = $this->paziente(['birth_date' => now()->subYears(40)->subDays(3)->toDateString()]);

        $this->come($this->admin())
            ->getJson("/api/v1/patients/{$paziente->id}")
            ->assertOk()
            ->assertJsonPath('data.age', 40);
    }

    public function test_l_archiviazione_di_un_paziente_e_un_soft_delete(): void
    {
        $paziente = $this->paziente();

        $this->come($this->admin())
            ->deleteJson("/api/v1/patients/{$paziente->id}")
            ->assertOk();

        $this->assertSoftDeleted('patients', ['id' => $paziente->id]);
    }

    /* =====================================================================
     | Profilo medico
     * ===================================================================*/

    public function test_l_admin_crea_un_profilo_medico(): void
    {
        $account = User::factory()->doctor()->create();

        $this->come($this->admin())
            ->postJson('/api/v1/doctors', [
                'user_id'          => $account->id,
                'specialization'   => 'Dermatologia',
                'license_number'   => 'MED123456',
                'consultation_fee' => 110.50,
                'slot_duration'    => 20,
                'available_online' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.specialization', 'Dermatologia')
            ->assertJsonPath('data.consultation_fee', 110.5)
            ->assertJsonPath('data.slot_duration', 20);

        $this->assertDatabaseHas('doctors', ['user_id' => $account->id, 'license_number' => 'MED123456']);
    }

    public function test_il_paziente_non_puo_creare_un_medico(): void
    {
        $account = User::factory()->doctor()->create();

        $this->comePaziente($this->paziente())
            ->postJson('/api/v1/doctors', ['user_id' => $account->id, 'specialization' => 'Cardiologia'])
            ->assertForbidden();
    }

    public function test_l_elenco_medici_e_visibile_a_tutti_i_ruoli(): void
    {
        $this->medico();
        $this->medico();

        foreach ([$this->admin(), $this->medico()->user, $this->paziente()->user] as $utente) {
            $this->come($utente)
                ->getJson('/api/v1/doctors')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'specialization', 'consultation_fee']]]);
        }
    }

    /**
     * Regressione: un profilo medico il cui account e' stato archiviato non deve
     * comparire in elenco. Compariva senza nome e non era prenotabile; nel caso
     * peggiore faceva fallire la serializzazione dell'intera lista, lasciando il
     * paziente con una schermata "nessun medico disponibile".
     */
    public function test_l_elenco_medici_esclude_i_profili_senza_account_valido(): void
    {
        $valido  = $this->medico([], ['name' => 'Dott. Valido']);
        $orfano  = $this->medico([], ['name' => 'Dott. Archiviato']);

        $orfano->user->delete(); // soft delete dell'account

        $risposta = $this->comePaziente($this->paziente())
            ->getJson('/api/v1/doctors')
            ->assertOk();

        $id = collect($risposta->json('data'))->pluck('id')->all();

        $this->assertContains($valido->id, $id);
        $this->assertNotContains($orfano->id, $id, 'Un profilo senza account non e\' prenotabile.');
    }

    public function test_l_elenco_medici_si_filtra_per_specializzazione_e_ricerca(): void
    {
        $cardiologo  = $this->medico(['specialization' => 'Cardiologia'], ['name' => 'Anna Bianchi']);
        $dermatologo = $this->medico(['specialization' => 'Dermatologia'], ['name' => 'Marco Gialli']);

        $paziente = $this->paziente();

        $this->comePaziente($paziente)
            ->getJson('/api/v1/doctors?specialization=Cardiologia')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cardiologo->id);

        $this->comePaziente($paziente)
            ->getJson('/api/v1/doctors?q=Gialli')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dermatologo->id);
    }

    public function test_l_elenco_delle_specializzazioni_alimenta_i_filtri(): void
    {
        $this->medico(['specialization' => 'Cardiologia']);
        $this->medico(['specialization' => 'Cardiologia']);
        $this->medico(['specialization' => 'Ortopedia']);

        $risposta = $this->comePaziente($this->paziente())
            ->getJson('/api/v1/doctors/specializations')
            ->assertOk();

        $this->assertSame(['Cardiologia', 'Ortopedia'], $risposta->json('data'));
    }

    public function test_il_medico_aggiorna_il_proprio_orario_settimanale(): void
    {
        $medico = $this->medico([], [], conOrario: false);

        $this->comeMedico($medico)
            ->putJson("/api/v1/doctors/{$medico->id}", [
                'bio'       => 'Specialista in aritmie.',
                'schedules' => [
                    ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '13:00'],
                    ['weekday' => 3, 'start_time' => '14:00', 'end_time' => '18:00'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.bio', 'Specialista in aritmie.')
            ->assertJsonCount(2, 'data.schedules');

        $this->assertDatabaseCount('doctor_schedules', 2);
        $this->assertDatabaseHas('doctor_schedules', ['doctor_id' => $medico->id, 'weekday' => 3]);
    }

    public function test_l_orario_settimanale_viene_sostituito_non_accodato(): void
    {
        $medico = $this->medico(); // parte con 7 fasce, una per giorno

        $this->assertDatabaseCount('doctor_schedules', 7);

        $this->comeMedico($medico)
            ->putJson("/api/v1/doctors/{$medico->id}", [
                'schedules' => [['weekday' => 2, 'start_time' => '10:00', 'end_time' => '12:00']],
            ])
            ->assertOk();

        $this->assertDatabaseCount('doctor_schedules', 1);
    }

    public function test_un_medico_non_puo_toccare_il_profilo_di_un_collega(): void
    {
        $medico  = $this->medico();
        $collega = $this->medico();

        $this->comeMedico($medico)
            ->putJson("/api/v1/doctors/{$collega->id}", ['bio' => 'Modifica non autorizzata'])
            ->assertForbidden();
    }

    public function test_l_orario_di_fine_deve_seguire_quello_di_inizio(): void
    {
        $medico = $this->medico([], [], conOrario: false);

        $this->comeMedico($medico)
            ->putJson("/api/v1/doctors/{$medico->id}", [
                'schedules' => [['weekday' => 1, 'start_time' => '18:00', 'end_time' => '09:00']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedules.0.end_time');
    }

    /* =====================================================================
     | Disponibilita'
     * ===================================================================*/

    public function test_la_disponibilita_espone_gli_slot_del_giorno(): void
    {
        $medico = $this->medico(['slot_duration' => 30], [], conOrario: false);
        $data   = $this->slotFuturo();

        DoctorSchedule::factory()
            ->weekday($data->dayOfWeekIso)
            ->fascia('09:00:00', '11:00:00')
            ->create(['doctor_id' => $medico->id]);

        $risposta = $this->comePaziente($this->paziente())
            ->getJson("/api/v1/doctors/{$medico->id}/availability?date={$data->toDateString()}")
            ->assertOk()
            ->assertJsonStructure(['date', 'doctor', 'slots' => [['start', 'end', 'label', 'available']]]);

        // Due ore con slot da 30 minuti = 4 slot, tutti liberi
        $this->assertCount(4, $risposta->json('slots'));
        $this->assertTrue(collect($risposta->json('slots'))->every(fn ($s) => $s['available'] === true));
    }

    public function test_uno_slot_gia_prenotato_risulta_occupato(): void
    {
        $medico = $this->medico(['slot_duration' => 30], [], conOrario: false);
        $data   = $this->slotFuturo(9, 0);

        DoctorSchedule::factory()
            ->weekday($data->dayOfWeekIso)
            ->fascia('09:00:00', '11:00:00')
            ->create(['doctor_id' => $medico->id]);

        Appointment::factory()->create([
            'doctor_id'        => $medico->id,
            'patient_id'       => $this->paziente()->id,
            'scheduled_at'     => $data,
            'duration_minutes' => 30,
        ]);

        $slots = $this->comePaziente($this->paziente())
            ->getJson("/api/v1/doctors/{$medico->id}/availability?date={$data->toDateString()}")
            ->assertOk()
            ->json('slots');

        $this->assertFalse($slots[0]['available'], 'Il primo slot risulta gia occupato.');
        $this->assertTrue($slots[1]['available']);
    }

    public function test_in_ferie_il_medico_non_offre_slot(): void
    {
        $medico = $this->medico();
        $data   = $this->slotFuturo();

        DoctorAbsence::factory()->nelGiorno($data->toDateString())->create(['doctor_id' => $medico->id]);

        $this->comePaziente($this->paziente())
            ->getJson("/api/v1/doctors/{$medico->id}/availability?date={$data->toDateString()}")
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_la_disponibilita_richiede_una_data_valida(): void
    {
        $medico = $this->medico();

        $this->comePaziente($this->paziente())
            ->getJson("/api/v1/doctors/{$medico->id}/availability")
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }
}
