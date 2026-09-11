<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'patient_id'  => $this->patient_id,
            'doctor_id'   => $this->doctor_id,
            'type'        => $this->type,
            'title'       => $this->title,
            'description' => $this->description,
            'vitals'      => $this->vitals ?? [],
            'icd10_code'  => $this->icd10_code,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'date'        => $this->recorded_at?->format('d/m/Y'),
            'is_editable' => $this->isEditable(),
            'doctor'      => new DoctorResource($this->whenLoaded('doctor')),
            'patient'     => new PatientResource($this->whenLoaded('patient')),
        ];
    }
}
