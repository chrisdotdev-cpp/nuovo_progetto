<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Prescription $prescription): bool
    {
        return $user->isAdmin()
            || ($user->isPatient() && $prescription->patient->user_id === $user->id)
            || ($user->isDoctor()  && $prescription->doctor_id === $user->doctor?->id);
    }

    public function create(User $user): bool
    {
        return $user->isDoctor();
    }

    /** Solo il medico prescrittore, e solo finche' la ricetta e' attiva. */
    public function update(User $user, Prescription $prescription): bool
    {
        return $user->isDoctor()
            && $prescription->doctor_id === $user->doctor?->id
            && $prescription->status === 'attiva';
    }

    public function delete(User $user, Prescription $prescription): bool
    {
        return $user->isAdmin();
    }
}
