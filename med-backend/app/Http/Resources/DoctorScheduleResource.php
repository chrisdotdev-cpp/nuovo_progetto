<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorScheduleResource extends JsonResource
{
    private const GIORNI = [1 => 'Lunedi', 2 => 'Martedi', 3 => 'Mercoledi', 4 => 'Giovedi', 5 => 'Venerdi', 6 => 'Sabato', 7 => 'Domenica'];

    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'doctor_id'    => $this->doctor_id,
            'weekday'      => $this->weekday,
            'weekday_name' => self::GIORNI[$this->weekday] ?? null,
            'start_time'   => substr((string) $this->start_time, 0, 5),
            'end_time'     => substr((string) $this->end_time, 0, 5),
            'active'       => $this->active,
        ];
    }
}
