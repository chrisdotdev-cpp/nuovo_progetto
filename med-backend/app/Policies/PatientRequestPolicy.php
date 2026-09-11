<?php

namespace App\Policies;

use App\Models\PatientRequest;
use App\Models\User;

class PatientRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PatientRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isPatient()) {
            return $request->patient->user_id === $user->id;
        }

        // Il medico vede le richieste assegnate a lui o ancora in coda generale
        return $user->isDoctor()
            && ($request->doctor_id === $user->doctor?->id || $request->doctor_id === null);
    }

    /** Solo il paziente apre una richiesta. */
    public function create(User $user): bool
    {
        return $user->isPatient();
    }

    public function update(User $user, PatientRequest $request): bool
    {
        // Il paziente puo' correggere finche' nessuno l'ha presa in carico
        return ($user->isPatient()
                && $request->patient->user_id === $user->id
                && $request->status === 'aperta')
            || $user->isAdmin();
    }

    /** Rispondere, prendere in carico e convertire spetta al medico. */
    public function respond(User $user, PatientRequest $request): bool
    {
        return $user->isDoctor()
            && ($request->doctor_id === null || $request->doctor_id === $user->doctor?->id);
    }

    public function delete(User $user, PatientRequest $request): bool
    {
        return $user->isAdmin();
    }
}
