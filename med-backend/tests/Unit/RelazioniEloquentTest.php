<?php

namespace Tests\Unit;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorAbsence;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaScenariSanitari;
use Tests\TestCase;

/**
 * Grafo delle relazioni.
 *
 * Sono i binari su cui corre tutto il resto: una chiave esterna sbagliata non
 * fa fallire il deploy, fa solo sparire dei dati dalle liste. Qui ogni
 * relazione viene percorsa in entrambe le direzioni.
 */
class RelazioniEloquentTest extends TestCase
{
    use RefreshDatabase, CreaScenariSanitari;

    /* =====================================================================
     | Account <-> profilo
     * ===================================================================*/

    public function test_l_account_paziente_raggiunge_la_propria_anagrafica(): void
    {
        $paziente = $this->paziente();
        $user     = $paziente->user;

        $this->assertInstanceOf(Patient::class, $user->patient);
        $this->assertSame($paziente->id, $user->patient->id);
        $this->assertNull($user->doctor, 'Un paziente non ha profilo medico.');
    }

    public function test_l_account_medico_raggiunge_il_proprio_profilo(): void
    {
        $medico = $this->medico();
        $user   = $medico->user;

        $this->assertInstanceOf(Doctor::class, $user->doctor);
        $this->assertSame($medico->id, $user->doctor->id);
        $this->assertNull($user->patient);
    }

    public function test_il_profilo_risale_all_account(): void
    {
        $paziente = $this->paziente();
        $medico   = $this->medico();

        $this->assertInstanceOf(User::class, $paziente->user);
        $this->assertSame(User::ROLE_PAZIENTE, $paziente->user->role);

        $this->assertInstanceOf(User::class, $medico->user);
        $this->assertSame(User::ROLE_MEDICO, $medico->user->role);
    }

    /* =====================================================================
     | Medico
     * ===================================================================*/

    public function test_il_medico_possiede_orari_assenze_e_appuntamenti(): void
    {
        $medico = $this->medico([], [], conOrario: false);

        DoctorSchedule::factory()->count(3)->sequence(
            ['weekday' => 1], ['weekday' => 2], ['weekday' => 3],
        )->create(['doctor_id' => $medico->id]);

        DoctorAbsence::factory()->create(['doctor_id' => $medico->id]);

        Appointment::factory()->count(2)->create([
            'doctor_id'  => $medico->id,
            'patient_id' => $this->paziente()->id,
        ]);

        $medico->refresh();

        $this->assertCount(3, $medico->schedules);
        $this->assertCount(1, $medico->absences);
        $this->assertCount(2, $medico->appointments);
        $this->assertInstanceOf(Doctor::class, $medico->schedules->first()->doctor);
    }

    public function test_il_medico_conosce_i_pazienti_che_ha_in_carico(): void
    {
        $medico = $this->medico();

        $this->paziente(['primary_doctor_id' => $medico->id]);
        $this->paziente(['primary_doctor_id' => $medico->id]);
        $this->paziente(); // senza medico di base

        $this->assertCount(2, $medico->fresh()->assignedPatients);
    }

    public function test_il_paziente_raggiunge_il_proprio_medico_di_base(): void
    {
        $medico   = $this->medico(['specialization' => 'Cardiologia']);
        $paziente = $this->paziente(['primary_doctor_id' => $medico->id]);

        $this->assertInstanceOf(Doctor::class, $paziente->primaryDoctor);
        $this->assertSame('Cardiologia', $paziente->primaryDoctor->specialization);
        $this->assertNull($this->paziente()->primaryDoctor);
    }

    /* =====================================================================
     | Appuntamento
     * ===================================================================*/

    public function test_l_appuntamento_collega_paziente_e_medico(): void
    {
        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->assertSame($paziente->id, $appuntamento->patient->id);
        $this->assertSame($medico->id, $appuntamento->doctor->id);

        // E i due profili lo ritrovano dalla loro parte
        $this->assertTrue($paziente->fresh()->appointments->contains($appuntamento));
        $this->assertTrue($medico->fresh()->appointments->contains($appuntamento));
    }

    public function test_l_appuntamento_ricorda_chi_lo_ha_annullato(): void
    {
        $admin = $this->admin();

        $appuntamento = Appointment::factory()->create([
            'doctor_id'    => $this->medico()->id,
            'patient_id'   => $this->paziente()->id,
            'status'       => Appointment::STATUS_ANNULLATO,
            'cancelled_by' => $admin->id,
        ]);

        $this->assertInstanceOf(User::class, $appuntamento->canceller);
        $this->assertSame($admin->id, $appuntamento->canceller->id);
    }

    public function test_un_appuntamento_appena_creato_non_ha_fattura_ne_sessione(): void
    {
        $appuntamento = Appointment::factory()->create([
            'doctor_id'  => $this->medico()->id,
            'patient_id' => $this->paziente()->id,
        ]);

        $this->assertNull($appuntamento->invoice);
        $this->assertNull($appuntamento->telemedicineSession);
        $this->assertNull($appuntamento->medicalRecord);
    }

    /* =====================================================================
     | Notifiche
     * ===================================================================*/

    public function test_le_notifiche_dell_utente_arrivano_dalla_piu_recente(): void
    {
        $user = User::factory()->create();

        $vecchia = AppNotification::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        $recente = AppNotification::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now(),
        ]);

        $notifiche = $user->fresh()->notifications;

        $this->assertCount(2, $notifiche);
        $this->assertSame($recente->id, $notifiche->first()->id, 'La relazione ordina gia dalla piu recente.');
        $this->assertSame($vecchia->id, $notifiche->last()->id);
        $this->assertSame($user->id, $recente->user->id);
    }

    /* =====================================================================
     | Cancellazioni
     * ===================================================================*/

    public function test_archiviare_un_account_non_cancella_i_dati_clinici(): void
    {
        $paziente = $this->paziente();

        $paziente->user->delete();

        $this->assertSoftDeleted('users', ['id' => $paziente->user_id]);
        $this->assertDatabaseHas('patients', ['id' => $paziente->id, 'deleted_at' => null]);
    }

    public function test_gli_orari_seguono_la_cancellazione_definitiva_del_medico(): void
    {
        $medico = $this->medico();

        $this->assertDatabaseCount('doctor_schedules', 7);

        $medico->forceDelete();

        $this->assertDatabaseCount('doctor_schedules', 0);
    }
}
