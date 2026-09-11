<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Creazione account da parte dell'amministrazione.
 * I campi profilo (medico o paziente) sono validati in modo condizionale sul ruolo.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role'     => ['required', 'in:admin,medico,paziente'],
            'status'   => ['nullable', 'in:attivo,sospeso,in_attesa'],
            'phone'    => ['nullable', 'string', 'max:30'],

            // Profilo medico
            'specialization'   => ['required_if:role,medico', 'nullable', 'string', 'max:120'],
            'license_number'   => ['nullable', 'string', 'max:50', 'unique:doctors,license_number'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'slot_duration'    => ['nullable', 'integer', 'min:5', 'max:240'],

            // Profilo paziente
            'codice_fiscale' => ['nullable', 'string', 'size:16', 'unique:patients,codice_fiscale'],
            'birth_date'     => ['nullable', 'date', 'before:today'],
            'gender'         => ['nullable', 'in:M,F,altro'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'              => 'Esiste gia\' un account con questa email.',
            'specialization.required_if'=> 'La specializzazione e\' obbligatoria per un medico.',
            'codice_fiscale.size'       => 'Il codice fiscale deve essere di 16 caratteri.',
        ];
    }
}
