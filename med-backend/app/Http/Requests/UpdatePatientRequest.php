<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            'codice_fiscale'          => ['nullable', 'string', 'size:16', Rule::unique('patients')->ignore($patientId)],
            'birth_date'              => ['nullable', 'date', 'before:today'],
            'gender'                  => ['nullable', 'in:M,F,altro'],
            'birth_place'             => ['nullable', 'string', 'max:255'],
            'address'                 => ['nullable', 'string', 'max:255'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'province'                => ['nullable', 'string', 'max:5'],
            'postal_code'             => ['nullable', 'string', 'max:10'],
            'blood_type'              => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,0+,0-'],
            'allergies'               => ['nullable', 'array'],
            'chronic_conditions'      => ['nullable', 'array'],
            'notes'                   => ['nullable', 'string'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            // Solo admin e medici possono cambiare il medico di base
            'primary_doctor_id'       => ['nullable', 'exists:doctors,id'],
        ];
    }
}
