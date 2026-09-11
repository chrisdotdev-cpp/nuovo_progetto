<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'patient_id'   => $this->patient_id,
            'doctor_id'    => $this->doctor_id,
            'subject'      => $this->subject,
            'description'  => $this->description,
            'priority'     => $this->priority,
            'status'       => $this->status,
            'response'     => $this->response,
            'responded_at' => $this->responded_at?->toIso8601String(),
            // Esiti: consentono al frontend di mostrare il link alla risorsa generata
            'appointment_id'  => $this->appointment_id,
            'prescription_id' => $this->prescription_id,
            // Dati dell'appuntamento generato: permettono di mostrare la data invece del solo ID
            'appointment'     => $this->whenLoaded('appointment', fn () => [
                'id'           => $this->appointment->id,
                'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
                'type'         => $this->appointment->type,
            ]),
            'attachments'  => RequestAttachmentResource::collection($this->whenLoaded('attachments')),
            'patient'      => new PatientResource($this->whenLoaded('patient')),
            'doctor'       => new DoctorResource($this->whenLoaded('doctor')),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
