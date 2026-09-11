<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Firma massiva dei documenti selezionati. */
class SignDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'ids'            => ['required', 'array', 'min:1'],
            'ids.*'          => ['integer', 'exists:documents,id'],
            'signature_type' => ['nullable', 'in:FEA,FEQ'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Seleziona almeno un documento da firmare.',
        ];
    }
}
