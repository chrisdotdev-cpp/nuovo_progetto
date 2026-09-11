<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['sometimes', 'in:diagnosi,referto,esame,nota,vaccinazione,intervento'],
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'vitals'      => ['nullable', 'array'],
            'icd10_code'  => ['nullable', 'string', 'max:10'],
        ];
    }
}
