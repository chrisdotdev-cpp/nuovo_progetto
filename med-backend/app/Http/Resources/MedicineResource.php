<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'active_ingredient'     => $this->active_ingredient,
            'aic_code'              => $this->aic_code,
            'form'                  => $this->form,
            'dosage'                => $this->dosage,
            'manufacturer'          => $this->manufacturer,
            'price'                 => (float) $this->price,
            'requires_prescription' => $this->requires_prescription,
            'stock_quantity'        => $this->stock_quantity,
            'min_stock'             => $this->min_stock,
            'stock_status'          => $this->stock_status,
            'batch'                 => $this->batch,
            'expiry_date'           => $this->expiry_date?->toDateString(),
            'active'                => $this->active,
        ];
    }
}
