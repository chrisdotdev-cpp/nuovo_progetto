<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\DoctorPolicy;
use App\Policies\PatientPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaScenariSanitari;
use Tests\TestCase;

/**
 * Policy provate in isolamento.
 *
 * I test Feature verificano che le policy siano *applicate*; qui si verifica
 * che siano *giuste*, compresi i rami che dall'HTTP sarebbero scomodi da
 * raggiungere (medico senza profilo, admin che cancella se stesso).
 */
class PolicyTest extends TestCase
{
    use RefreshDatabase, CreaScenariSanitari;

    /* =====================================================================
     | AppointmentPolicy
     * ===================================================================*/

    public function test_solo_i_partecipanti_vedono_l_appuntamento(): void
    {
        $policy = new AppointmentPolicy;

        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->assertTrue($policy->view($paziente->user, $appuntamento));
        $this->assertTrue($policy->view($medico->user, $appuntamento));
        $this->assertTrue($policy->view($this->admin(), $appuntamento));

        $this->assertFalse($policy->view($this->paziente()->user, $appuntamento));
        $this->assertFalse($policy->view($this->medico()->user, $appuntamento));
    }

    public function test_un_appuntamento_chiuso_non_e_modificabile_da_nessuno(): void
    {
        $policy = new AppointmentPolicy;

        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->completato()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->assertFalse($policy->update($medico->user, $appuntamento));
        $this->assertFalse($policy->update($paziente->user, $appuntamento));
        $this->assertFalse($policy->update($this->admin(), $appuntamento));
        $this->assertFalse($policy->cancel($paziente->user, $appuntamento));
    }

    public function test_il_cambio_di_stato_e_riservato_a_medico_e_admin(): void
    {
        $policy = new AppointmentPolicy;

        $medico   = $this->medico();
        $collega  = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->assertTrue($policy->changeStatus($this->admin(), $appuntamento));
        $this->assertTrue($policy->changeStatus($medico->user, $appuntamento));
        $this->assertFalse($policy->changeStatus($collega->user, $appuntamento), 'Non e la sua agenda.');
        $this->assertFalse($policy->changeStatus($paziente->user, $appuntamento));
    }

    public function test_un_medico_senza_profilo_non_e_partecipante(): void
    {
        $policy = new AppointmentPolicy;

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $this->medico()->id, 'patient_id' => $this->paziente()->id,
        ]);

        $senzaProfilo = User::factory()->doctor()->create();

        $this->assertFalse($policy->view($senzaProfilo, $appuntamento));
        $this->assertFalse($policy->changeStatus($senzaProfilo, $appuntamento));
    }

    public function test_solo_l_admin_elimina_definitivamente_un_appuntamento(): void
    {
        $policy = new AppointmentPolicy;

        $medico   = $this->medico();
        $paziente = $this->paziente();

        $appuntamento = Appointment::factory()->create([
            'doctor_id' => $medico->id, 'patient_id' => $paziente->id,
        ]);

        $this->assertTrue($policy->delete($this->admin(), $appuntamento));
        $this->assertFalse($policy->delete($medico->user, $appuntamento));
        $this->assertFalse($policy->delete($paziente->user, $appuntamento));
    }

    /* =====================================================================
     | PatientPolicy
     * ===================================================================*/

    public function test_l_elenco_pazienti_e_precluso_al_paziente(): void
    {
        $policy = new PatientPolicy;

        $this->assertTrue($policy->viewAny($this->admin()));
        $this->assertTrue($policy->viewAny($this->medico()->user));
        $this->assertFalse($policy->viewAny($this->paziente()->user));
    }

    public function test_il_paziente_vede_solo_la_propria_scheda(): void
    {
        $policy = new PatientPolicy;

        $io    = $this->paziente();
        $altro = $this->paziente();

        $this->assertTrue($policy->view($io->user, $io));
        $this->assertFalse($policy->view($io->user, $altro));
        $this->assertTrue($policy->update($io->user, $io));
        $this->assertFalse($policy->update($io->user, $altro));
    }

    public function test_il_medico_vede_assistiti_e_pazienti_visitati(): void
    {
        $policy = new PatientPolicy;

        $medico    = $this->medico();
        $assistito = $this->paziente(['primary_doctor_id' => $medico->id]);
        $visitato  = $this->paziente();
        $estraneo  = $this->paziente();

        Appointment::factory()->create(['doctor_id' => $medico->id, 'patient_id' => $visitato->id]);

        $this->assertTrue($policy->view($medico->user, $assistito));
        $this->assertTrue($policy->view($medico->user, $visitato));
        $this->assertFalse($policy->view($medico->user, $estraneo));
    }

    public function test_solo_l_admin_crea_e_archivia_anagrafiche(): void
    {
        $policy = new PatientPolicy;

        $paziente = $this->paziente();
        $medico   = $this->medico();

        $this->assertTrue($policy->create($this->admin()));
        $this->assertFalse($policy->create($medico->user));
        $this->assertFalse($policy->create($paziente->user));

        $this->assertTrue($policy->delete($this->admin(), $paziente));
        $this->assertFalse($policy->delete($medico->user, $paziente));
        $this->assertFalse($policy->delete($paziente->user, $paziente));
    }

    /* =====================================================================
     | DoctorPolicy
     * ===================================================================*/

    public function test_l_elenco_medici_e_leggibile_da_chiunque_sia_autenticato(): void
    {
        $policy = new DoctorPolicy;
        $medico = $this->medico();

        foreach ([$this->admin(), $this->medico()->user, $this->paziente()->user] as $utente) {
            $this->assertTrue($policy->viewAny($utente));
            $this->assertTrue($policy->view($utente, $medico));
        }
    }

    public function test_il_profilo_medico_lo_modificano_solo_l_interessato_e_l_admin(): void
    {
        $policy = new DoctorPolicy;

        $medico  = $this->medico();
        $collega = $this->medico();

        $this->assertTrue($policy->update($medico->user, $medico));
        $this->assertTrue($policy->update($this->admin(), $medico));
        $this->assertFalse($policy->update($collega->user, $medico));

        $this->assertTrue($policy->manageSchedule($medico->user, $medico));
        $this->assertFalse($policy->manageSchedule($collega->user, $medico));
        $this->assertFalse($policy->manageSchedule($this->paziente()->user, $medico));
    }

    /* =====================================================================
     | UserPolicy
     * ===================================================================*/

    public function test_la_gestione_account_e_riservata_all_amministrazione(): void
    {
        $policy = new UserPolicy;

        $admin    = $this->admin();
        $paziente = $this->paziente()->user;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertFalse($policy->viewAny($paziente));

        $this->assertTrue($policy->create($admin));
        $this->assertFalse($policy->create($paziente));

        $this->assertTrue($policy->manageRole($admin));
        $this->assertFalse($policy->manageRole($paziente));
    }

    public function test_ognuno_legge_e_aggiorna_se_stesso(): void
    {
        $policy = new UserPolicy;

        $tizio = $this->paziente()->user;
        $caio  = $this->paziente()->user;

        $this->assertTrue($policy->view($tizio, $tizio));
        $this->assertTrue($policy->update($tizio, $tizio));
        $this->assertFalse($policy->view($tizio, $caio));
        $this->assertFalse($policy->update($tizio, $caio));
    }

    public function test_un_admin_non_puo_cancellare_se_stesso(): void
    {
        $policy = new UserPolicy;

        $admin      = $this->admin();
        $altroAdmin = $this->admin();

        $this->assertTrue($policy->delete($admin, $altroAdmin));
        $this->assertFalse(
            $policy->delete($admin, $admin),
            'Impedisce di restare senza amministratori.'
        );
    }
}
