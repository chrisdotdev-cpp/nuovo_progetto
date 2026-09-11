<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Apertura di una richiesta di consulto da parte del paziente. */
class StorePatientRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id'     => ['nullable', 'exists:doctors,id'],
            'subject'       => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string', 'min:10'],
            'priority'      => ['required', 'in:bassa,media,alta'],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.min'  => 'Descrivi il problema con almeno 10 caratteri.',
            'attachments.max'  => 'Puoi allegare al massimo 5 file.',
        ];
    }
}
