<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Output utente. Le date sono sempre ISO 8601: Vue le formatta con Intl,
 * il backend non deve occuparsi della localizzazione.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'role'          => $this->role,
            'status'        => $this->status,
            'phone'         => $this->phone,
            'avatar_url'    => $this->avatar_path ? url("/api/v1/files/avatar/{$this->id}") : null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            // Profili collegati, inclusi solo se gia' caricati (evita query N+1)
            'patient'       => new PatientResource($this->whenLoaded('patient')),
            'doctor'        => new DoctorResource($this->whenLoaded('doctor')),
        ];
    }
}
