<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Prenotazione. Il paziente non puo' prenotare per altri:
 * il patient_id viene forzato al proprio profilo in prepareForValidation.
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()->role === User::ROLE_PAZIENTE) {
            $this->merge(['patient_id' => $this->user()->patient?->id]);
        }
    }

    public function rules(): array
    {
        return [
            'patient_id'       => ['required', 'exists:patients,id'],
            'doctor_id'        => ['required', 'exists:doctors,id'],
            'scheduled_at'     => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'type'             => ['nullable', 'in:visita,controllo,telemedicina,urgenza'],
            'reason'           => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after'   => 'Non puoi prenotare una data passata.',
            'doctor_id.exists'     => 'Il medico selezionato non esiste.',
            'patient_id.required'  => 'Profilo paziente non trovato per questo account.',
        ];
    }
}
