<?php

namespace App\Policies;

use App\Models\Medicine;
use App\Models\User;

class MedicinePolicy
{
    /** Il medico consulta l'anagrafica per prescrivere, l'admin gestisce il magazzino. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDoctor();
    }

    public function view(User $user, Medicine $medicine): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Medicine $medicine): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Medicine $medicine): bool
    {
        return $user->isAdmin();
    }

    /** Carichi e scarichi di magazzino. */
    public function manageStock(User $user): bool
    {
        return $user->isAdmin();
    }
}
