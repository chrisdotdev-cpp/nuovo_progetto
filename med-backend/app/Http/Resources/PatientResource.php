<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'user_id'            => $this->user_id,
            'name'               => $this->whenLoaded('user', fn () => $this->user->name),
            'email'              => $this->whenLoaded('user', fn () => $this->user->email),
            'phone'              => $this->whenLoaded('user', fn () => $this->user->phone),
            'codice_fiscale'     => $this->codice_fiscale,
            'birth_date'         => $this->birth_date?->toDateString(),
            'age'                => $this->age,
            'gender'             => $this->gender,
            'birth_place'        => $this->birth_place,
            'address'            => $this->address,
            'city'               => $this->city,
            'province'           => $this->province,
            'postal_code'        => $this->postal_code,
            'blood_type'         => $this->blood_type,
            'allergies'          => $this->allergies ?? [],
            'chronic_conditions' => $this->chronic_conditions ?? [],
            'notes'              => $this->notes,
            'emergency_contact'  => [
                'name'  => $this->emergency_contact_name,
                'phone' => $this->emergency_contact_phone,
            ],
            'primary_doctor'     => new DoctorResource($this->whenLoaded('primaryDoctor')),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
