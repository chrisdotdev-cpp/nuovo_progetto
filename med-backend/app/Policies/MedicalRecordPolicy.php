<?php

namespace App\Policies;

use App\Models\MedicalRecord;
use App\Models\User;

class MedicalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MedicalRecord $record): bool
    {
        return $user->isAdmin()
            || ($user->isPatient() && $record->patient->user_id === $user->id)
            || ($user->isDoctor()  && $this->doctorHasAccess($user, $record));
    }

    /** Solo i medici scrivono nella cartella clinica. */
    public function create(User $user): bool
    {
        return $user->isDoctor();
    }

    /** Modificabile solo dall'autore ed entro la finestra di rettifica. */
    public function update(User $user, MedicalRecord $record): bool
    {
        return $user->isDoctor()
            && $record->doctor_id === $user->doctor?->id
            && $record->isEditable();
    }

    public function delete(User $user, MedicalRecord $record): bool
    {
        return $user->isAdmin();
    }

    private function doctorHasAccess(User $user, MedicalRecord $record): bool
    {
        $doctorId = $user->doctor?->id;

        if ($doctorId === null) {
            return false;
        }

        // L'autore della voce oppure un medico che segue il paziente
        return $record->doctor_id === $doctorId
            || $record->patient->primary_doctor_id === $doctorId
            || $record->patient->appointments()->where('doctor_id', $doctorId)->exists();
    }
}
