<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'                 => ['required', 'exists:users,id', 'unique:patients,user_id'],
            'codice_fiscale'          => ['nullable', 'string', 'size:16', 'unique:patients,codice_fiscale'],
            'birth_date'              => ['nullable', 'date', 'before:today'],
            'gender'                  => ['nullable', 'in:M,F,altro'],
            'birth_place'             => ['nullable', 'string', 'max:255'],
            'address'                 => ['nullable', 'string', 'max:255'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'province'                => ['nullable', 'string', 'max:5'],
            'postal_code'             => ['nullable', 'string', 'max:10'],
            'blood_type'              => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,0+,0-'],
            'allergies'               => ['nullable', 'array'],
            'allergies.*'             => ['string', 'max:120'],
            'chronic_conditions'      => ['nullable', 'array'],
            'chronic_conditions.*'    => ['string', 'max:120'],
            'notes'                   => ['nullable', 'string'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'primary_doctor_id'       => ['nullable', 'exists:doctors,id'],
        ];
    }
}
