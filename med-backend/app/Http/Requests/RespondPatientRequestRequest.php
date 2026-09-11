<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Risposta del medico a una richiesta.
 * Puo' contenere il solo parere oppure gia' l'esito (appuntamento / prescrizione).
 */
class RespondPatientRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'min:5'],
            'status'   => ['nullable', 'in:in_carico,risposta,chiusa'],
        ];
    }
}
