<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'issue_date'     => ['nullable', 'date'],
            'due_date'       => ['nullable', 'date', 'after_or_equal:issue_date'],
            'tax_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status'         => ['nullable', 'in:bozza,emessa,pagata,parziale,scaduta,stornata'],
            'notes'          => ['nullable', 'string'],

            'items'                => ['required', 'array', 'min:1'],
            'items.*.description'  => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Inserisci almeno una voce in fattura.',
        ];
    }
}
