<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at'     => ['sometimes', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'type'             => ['sometimes', 'in:visita,controllo,telemedicina,urgenza'],
            // Il cambio di stato passa dalla policy changeStatus (solo medico/admin)
            'status'           => ['sometimes', 'in:in_attesa,confermato,completato,annullato,assente'],
            'reason'           => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
