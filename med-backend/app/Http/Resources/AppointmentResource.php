<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'patient_id'       => $this->patient_id,
            'doctor_id'        => $this->doctor_id,
            'scheduled_at'     => $this->scheduled_at?->toIso8601String(),
            'ends_at'          => $this->ends_at,
            // Campi pronti per la UI, cosi' le liste non ricalcolano nulla
            'date'             => $this->scheduled_at?->format('d/m/Y'),
            'time'             => $this->scheduled_at?->format('H:i'),
            'duration_minutes' => $this->duration_minutes,
            'type'             => $this->type,
            'status'           => $this->status,
            'reason'           => $this->reason,
            'notes'            => $this->when(
                $request->user()?->isAdmin() || $request->user()?->isDoctor(),
                $this->notes
            ),
            'cancellation_reason' => $this->cancellation_reason,
            'is_editable'      => $this->isEditable(),
            'patient'          => new PatientResource($this->whenLoaded('patient')),
            'doctor'           => new DoctorResource($this->whenLoaded('doctor')),
            'telemedicine'     => new TelemedicineSessionResource($this->whenLoaded('telemedicineSession')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
