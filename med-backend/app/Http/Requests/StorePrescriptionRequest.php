<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'     => ['required', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'notes'          => ['nullable', 'string'],
            'issued_at'      => ['nullable', 'date'],
            'valid_until'    => ['nullable', 'date', 'after:issued_at'],

            // Una ricetta senza farmaci non ha senso: almeno una riga
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.medicine_id'   => ['nullable', 'exists:medicines,id'],
            'items.*.name'          => ['required', 'string', 'max:255'],
            'items.*.dosage'        => ['nullable', 'string', 'max:100'],
            'items.*.frequency'     => ['nullable', 'string', 'max:100'],
            'items.*.duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'items.*.quantity'      => ['nullable', 'integer', 'min:1'],
            'items.*.notes'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'      => 'Aggiungi almeno un farmaco alla prescrizione.',
            'items.*.name.required' => 'Il nome del farmaco e\' obbligatorio.',
        ];
    }
}
