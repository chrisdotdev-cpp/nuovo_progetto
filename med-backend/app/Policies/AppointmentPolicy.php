<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // la query viene comunque filtrata per ruolo nel controller
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->isParticipant($user, $appointment);
    }

    public function create(User $user): bool
    {
        // Il paziente prenota per se', medico e admin prenotano per conto terzi
        return true;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if (! $appointment->isEditable()) {
            return false;
        }

        return $this->isParticipant($user, $appointment);
    }

    /** Solo medico e admin dichiarano un appuntamento completato o assente. */
    public function changeStatus(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin()
            || ($user->isDoctor() && $appointment->doctor_id === $user->doctor?->id);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->isParticipant($user, $appointment) && $appointment->isEditable();
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin();
    }

    /** Vero se l'utente e' il paziente, il medico dell'appuntamento o un admin. */
    private function isParticipant(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin()
            || ($user->isPatient() && $appointment->patient_id === $user->patient?->id)
            || ($user->isDoctor()  && $appointment->doctor_id  === $user->doctor?->id);
    }
}
