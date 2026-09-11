<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorizzazione gestita dalla policy nel controller
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'   => ['sometimes', 'string', 'max:255'],
            'email'  => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'phone'  => ['nullable', 'string', 'max:30'],
            // Ruolo e stato solo per gli admin: il controllo e' nella UserPolicy::manageRole
            'role'   => ['sometimes', 'in:admin,medico,paziente'],
            'status' => ['sometimes', 'in:attivo,sospeso,in_attesa'],
        ];
    }
}
