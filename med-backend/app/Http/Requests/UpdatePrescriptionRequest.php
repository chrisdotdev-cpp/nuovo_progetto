<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'      => ['sometimes', 'in:attiva,completata,annullata,scaduta'],
            'notes'       => ['nullable', 'string'],
            'valid_until' => ['nullable', 'date'],

            'items'                 => ['sometimes', 'array', 'min:1'],
            'items.*.medicine_id'   => ['nullable', 'exists:medicines,id'],
            'items.*.name'          => ['required_with:items', 'string', 'max:255'],
            'items.*.dosage'        => ['nullable', 'string', 'max:100'],
            'items.*.frequency'     => ['nullable', 'string', 'max:100'],
            'items.*.duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'items.*.quantity'      => ['nullable', 'integer', 'min:1'],
            'items.*.notes'         => ['nullable', 'string'],
        ];
    }
}
