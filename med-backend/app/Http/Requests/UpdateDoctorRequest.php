<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $doctorId = $this->route('doctor')?->id;

        return [
            'specialization'   => ['sometimes', 'string', 'max:120'],
            'license_number'   => ['nullable', 'string', 'max:50', Rule::unique('doctors')->ignore($doctorId)],
            'bio'              => ['nullable', 'string'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'slot_duration'    => ['nullable', 'integer', 'min:5', 'max:240'],
            'available_online' => ['nullable', 'boolean'],

            // Aggiornamento in blocco dell'orario settimanale
            'schedules'              => ['nullable', 'array'],
            'schedules.*.weekday'    => ['required_with:schedules', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.end_time'   => ['required_with:schedules', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.active'     => ['nullable', 'boolean'],
        ];
    }
}
