<?php

namespace App\Policies;

use App\Models\User;

/**
 * La gestione degli account e' riservata all'amministrazione.
 * Ogni utente puo' comunque leggere e aggiornare se stesso.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin() || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin() || $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        // Nessuno puo' cancellare se stesso: evita di restare senza amministratori
        return $user->isAdmin() && $user->id !== $target->id;
    }

    /** Solo l'admin puo' cambiare ruolo e stato di un account. */
    public function manageRole(User $user): bool
    {
        return $user->isAdmin();
    }
}
