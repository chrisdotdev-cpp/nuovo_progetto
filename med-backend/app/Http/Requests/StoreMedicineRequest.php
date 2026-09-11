<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $medicineId = $this->route('medicine')?->id;

        return [
            'name'                  => ['required', 'string', 'max:255'],
            'active_ingredient'     => ['nullable', 'string', 'max:255'],
            'aic_code'              => ['nullable', 'string', 'max:20', Rule::unique('medicines')->ignore($medicineId)],
            'form'                  => ['nullable', 'string', 'max:60'],
            'dosage'                => ['nullable', 'string', 'max:60'],
            'manufacturer'          => ['nullable', 'string', 'max:255'],
            'price'                 => ['nullable', 'numeric', 'min:0'],
            'requires_prescription' => ['nullable', 'boolean'],
            // La giacenza iniziale si imposta qui, poi solo tramite movimenti
            'stock_quantity'        => ['nullable', 'integer', 'min:0'],
            'min_stock'             => ['nullable', 'integer', 'min:0'],
            'batch'                 => ['nullable', 'string', 'max:60'],
            'expiry_date'           => ['nullable', 'date', 'after:today'],
            'active'                => ['nullable', 'boolean'],
        ];
    }
}
