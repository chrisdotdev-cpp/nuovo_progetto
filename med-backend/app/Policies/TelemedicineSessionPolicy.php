<?php

namespace App\Policies;

use App\Models\TelemedicineSession;
use App\Models\User;

class TelemedicineSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TelemedicineSession $session): bool
    {
        return $this->isParticipant($user, $session);
    }

    public function create(User $user): bool
    {
        return $user->isDoctor() || $user->isAdmin();
    }

    /** L'accesso alla stanza richiede di essere partecipante e nella finestra oraria. */
    public function join(User $user, TelemedicineSession $session): bool
    {
        return $this->isParticipant($user, $session) && $session->isJoinable();
    }

    /** Solo il medico apre e chiude formalmente la sessione. */
    public function manage(User $user, TelemedicineSession $session): bool
    {
        return $user->isDoctor() && $session->appointment->doctor_id === $user->doctor?->id;
    }

    private function isParticipant(User $user, TelemedicineSession $session): bool
    {
        $appointment = $session->appointment;

        return $user->isAdmin()
            || ($user->isPatient() && $appointment->patient_id === $user->patient?->id)
            || ($user->isDoctor()  && $appointment->doctor_id  === $user->doctor?->id);
    }
}
