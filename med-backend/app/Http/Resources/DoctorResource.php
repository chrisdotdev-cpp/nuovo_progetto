<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            /*
             * Nome sempre disponibile: il frontend non deve fare join a mano.
             *
             * L'operatore ?-> non e' ridondante: se l'account collegato e' stato
             * archiviato (soft delete su users) la relazione e' caricata ma vale
             * null, e un accesso diretto a ->name farebbe fallire con 500 la
             * serializzazione dell'INTERA lista. Un singolo record incoerente
             * svuoterebbe cosi' l'elenco medici del paziente.
             */
            'name'             => $this->whenLoaded('user', fn () => $this->user?->name),
            'email'            => $this->whenLoaded('user', fn () => $this->user?->email),
            'specialization'   => $this->specialization,
            'license_number'   => $this->license_number,
            'bio'              => $this->bio,
            'consultation_fee' => (float) $this->consultation_fee,
            'slot_duration'    => $this->slot_duration,
            'available_online' => $this->available_online,
            'schedules'        => DoctorScheduleResource::collection($this->whenLoaded('schedules')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
