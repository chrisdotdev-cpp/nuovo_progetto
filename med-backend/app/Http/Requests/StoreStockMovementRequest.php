<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'type'     => ['required', 'in:carico,scarico,reso,scaduto,rettifica'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason'   => ['nullable', 'string', 'max:255'],
        ];
    }
}
