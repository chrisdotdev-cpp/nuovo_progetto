<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Il paziente vede solo i documenti a lui intestati
        if ($user->isPatient()) {
            return $document->patient?->user_id === $user->id;
        }

        // Il medico vede i documenti clinici dei suoi pazienti, non quelli amministrativi
        return $user->isDoctor()
            && in_array($document->category, ['referto', 'consenso'], true)
            && $document->patient !== null;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isDoctor() || $user->isPatient();
    }

    public function update(User $user, Document $document): bool
    {
        // Un documento firmato e' immutabile: si carica una nuova versione
        return ! $document->isSigned()
            && ($user->isAdmin() || $document->uploaded_by === $user->id);
    }

    /** La firma qualificata e' prerogativa di amministrazione e direzione sanitaria. */
    public function sign(User $user, Document $document): bool
    {
        return $user->isAdmin() && ! $document->isSigned();
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin() && ! $document->isSigned();
    }
}
