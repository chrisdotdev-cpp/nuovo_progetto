<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        // Il paziente non ha accesso all'elenco: vede solo la propria scheda
        return $user->isAdmin() || $user->isDoctor();
    }

    public function view(User $user, Patient $patient): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isPatient()) {
            return $patient->user_id === $user->id;
        }

        // Il medico accede alla scheda se e' il medico di base
        // oppure se ha almeno un appuntamento con quel paziente
        $doctorId = $user->doctor?->id;

        return $doctorId !== null && (
            $patient->primary_doctor_id === $doctorId
            || $patient->appointments()->where('doctor_id', $doctorId)->exists()
        );
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->isAdmin()
            || ($user->isPatient() && $patient->user_id === $user->id)
            || ($user->isDoctor() && $this->view($user, $patient));
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->isAdmin();
    }
}
