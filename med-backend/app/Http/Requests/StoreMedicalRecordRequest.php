<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRecordRequest extends FormRequest
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
            'type'           => ['required', 'in:diagnosi,referto,esame,nota,vaccinazione,intervento'],
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            // Parametri vitali liberi ma tipizzati: {"pressione":"120/80","peso":72}
            'vitals'         => ['nullable', 'array'],
            'icd10_code'     => ['nullable', 'string', 'max:10'],
            'recorded_at'    => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'recorded_at.before_or_equal' => 'Non e\' possibile registrare una voce con data futura.',
        ];
    }
}
