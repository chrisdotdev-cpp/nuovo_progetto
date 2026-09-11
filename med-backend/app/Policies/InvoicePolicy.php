<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isPatient();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->isPatient() && $invoice->patient->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Una fattura gia' incassata non si tocca: si emette una nota di credito. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && $invoice->status !== 'pagata';
    }

    /** Il paziente puo' registrare un pagamento solo sulle proprie fatture. */
    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->isPatient() && $invoice->patient->user_id === $user->id);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && $invoice->payments()->doesntExist();
    }
}
