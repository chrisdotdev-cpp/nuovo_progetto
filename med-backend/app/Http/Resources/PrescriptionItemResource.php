<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'medicine_id'   => $this->medicine_id,
            'name'          => $this->name,
            'dosage'        => $this->dosage,
            'frequency'     => $this->frequency,
            'duration_days' => $this->duration_days,
            'quantity'      => $this->quantity,
            'notes'         => $this->notes,
            'medicine'      => new MedicineResource($this->whenLoaded('medicine')),
        ];
    }
}
