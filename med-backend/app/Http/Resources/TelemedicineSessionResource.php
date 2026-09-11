<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelemedicineSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'appointment_id'   => $this->appointment_id,
            // Il codice stanza si espone solo a chi puo' effettivamente entrare
            'room_code'        => $this->when($this->isJoinable(), $this->room_code),
            'status'           => $this->status,
            'is_joinable'      => $this->isJoinable(),
            'started_at'       => $this->started_at?->toIso8601String(),
            'ended_at'         => $this->ended_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'notes'            => $this->notes,
            'appointment'      => new AppointmentResource($this->whenLoaded('appointment')),
        ];
    }
}
