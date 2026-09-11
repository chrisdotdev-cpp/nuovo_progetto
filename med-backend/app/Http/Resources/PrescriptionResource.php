<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'patient_id'  => $this->patient_id,
            'doctor_id'   => $this->doctor_id,
            'status'      => $this->status,
            'notes'       => $this->notes,
            'issued_at'   => $this->issued_at?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_expired'  => $this->is_expired,
            'items'       => PrescriptionItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'patient'     => new PatientResource($this->whenLoaded('patient')),
            'doctor'      => new DoctorResource($this->whenLoaded('doctor')),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
