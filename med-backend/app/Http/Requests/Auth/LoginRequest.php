<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email', 'max:255'],
            'password'    => ['required', 'string'],
            // Il frontend invia il ruolo scelto nella schermata di login:
            // serve a impedire che un paziente entri dalla porta dell'admin.
            'role'        => ['nullable', 'in:admin,medico,paziente'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'L\'email e\' obbligatoria.',
            'email.email'       => 'Inserisci un indirizzo email valido.',
            'password.required' => 'La password e\' obbligatoria.',
        ];
    }
}
