<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'          => ['required', 'exists:users,id', 'unique:doctors,user_id'],
            'specialization'   => ['required', 'string', 'max:120'],
            'license_number'   => ['nullable', 'string', 'max:50', 'unique:doctors,license_number'],
            'bio'              => ['nullable', 'string'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'slot_duration'    => ['nullable', 'integer', 'min:5', 'max:240'],
            'available_online' => ['nullable', 'boolean'],
        ];
    }
}
