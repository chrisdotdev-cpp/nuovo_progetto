<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'method'          => ['required', 'in:carta,bonifico,contanti,satispay,assicurazione'],
            'status'          => ['nullable', 'in:in_attesa,completato,fallito,rimborsato'],
            'transaction_ref' => ['nullable', 'string', 'max:255'],
            'paid_at'         => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'L\'importo deve essere maggiore di zero.',
        ];
    }
}
