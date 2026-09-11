<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

class DoctorPolicy
{
    /** L'elenco medici e' pubblico agli utenti autenticati: serve per prenotare. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return $user->isAdmin() || $doctor->user_id === $user->id;
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        return $user->isAdmin();
    }

    /** Solo il medico stesso o l'admin gestiscono orari e assenze. */
    public function manageSchedule(User $user, Doctor $doctor): bool
    {
        return $user->isAdmin() || $doctor->user_id === $user->id;
    }
}
