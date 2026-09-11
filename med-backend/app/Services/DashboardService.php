<?php

namespace App\Services;

use App\Http\Resources\AppointmentResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PatientRequestResource;
use App\Http\Resources\UserResource;
use App\Models\Appointment;
use App\Models\AppNotification;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\Prescription;
use App\Models\User;

/**
 * Aggregati per le viste "Panoramica" dei tre ruoli.
 * Una sola chiamata API per dashboard: meno round-trip, meno stati da gestire in Vue.
 */
class DashboardService
{
    public function forUser(User $user): array
    {
        return match ($user->role) {
            User::ROLE_PAZIENTE => $this->patient($user),
            User::ROLE_MEDICO   => $this->doctor($user),
            default             => $this->admin(),
        };
    }

    private function patient(User $user): array
    {
        $patient = $user->patient;

        if (! $patient) {
            return ['stats' => [], 'appuntamenti' => [], 'documenti' => []];
        }

        $next = Appointment::with('doctor.user')
            ->where('patient_id', $patient->id)
            ->upcoming()
            ->first();

        return [
            'stats' => [
                'prossima_visita'   => $next?->scheduled_at?->toIso8601String(),
                'referti'           => MedicalRecord::where('patient_id', $patient->id)->where('type', 'referto')->count(),
                'prescrizioni'      => Prescription::where('patient_id', $patient->id)->active()->count(),
                'messaggi_non_letti'=> AppNotification::where('user_id', $user->id)->unread()->count(),
                // Residuo reale (totale meno acconti), non il lordo delle fatture aperte
                'da_pagare'         => Invoice::outstanding($patient->id),
                'fatture_aperte'    => Invoice::where('patient_id', $patient->id)->unpaid()->count(),
            ],
            // Le Resource garantiscono al frontend lo stesso formato degli altri endpoint
            'appuntamenti' => AppointmentResource::collection(
                Appointment::with('doctor.user')
                    ->where('patient_id', $patient->id)
                    ->upcoming()->limit(5)->get()
            ),
            'documenti' => DocumentResource::collection(
                Document::where('patient_id', $patient->id)->latest()->limit(5)->get()
            ),
        ];
    }

    private function doctor(User $user): array
    {
        $doctor = $user->doctor;

        if (! $doctor) {
            return ['stats' => [], 'agenda_oggi' => [], 'richieste' => []];
        }

        return [
            'stats' => [
                'appuntamenti_oggi'  => Appointment::where('doctor_id', $doctor->id)
                                          ->whereDate('scheduled_at', today())
                                          ->where('status', '!=', Appointment::STATUS_ANNULLATO)->count(),
                'pazienti_seguiti'   => Patient::ofDoctor($doctor->id)->count(),
                'richieste_aperte'   => PatientRequest::where(fn ($q) => $q->where('doctor_id', $doctor->id)->orWhereNull('doctor_id'))
                                          ->whereIn('status', ['aperta', 'in_carico'])->count(),
                'prescrizioni_mese'  => Prescription::where('doctor_id', $doctor->id)
                                          ->whereMonth('issued_at', now()->month)
                                          ->whereYear('issued_at', now()->year)->count(),
            ],
            'agenda_oggi' => AppointmentResource::collection(
                Appointment::with('patient.user')
                    ->where('doctor_id', $doctor->id)
                    ->whereDate('scheduled_at', today())
                    ->orderBy('scheduled_at')->get()
            ),
            'richieste' => PatientRequestResource::collection(
                PatientRequest::with('patient.user')
                    ->where(fn ($q) => $q->where('doctor_id', $doctor->id)->orWhereNull('doctor_id'))
                    ->whereIn('status', ['aperta', 'in_carico'])
                    ->triageOrder()->limit(5)->get()
            ),
        ];
    }

    private function admin(): array
    {
        $inizioMese = now()->startOfMonth()->toDateString();
        $oggi       = now()->toDateString();

        // Una sola query per i due aggregati del mese: emesso e realmente incassato
        $mese = Invoice::period($inizioMese, $oggi)
            ->selectRaw('COALESCE(SUM(total), 0) as fatturato, COALESCE(SUM(paid_amount), 0) as incassato')
            ->first();

        return [
            'stats' => [
                'utenti_totali'      => User::count(),
                'pazienti'           => User::role(User::ROLE_PAZIENTE)->count(),
                'medici'             => User::role(User::ROLE_MEDICO)->count(),
                'appuntamenti_oggi'  => Appointment::whereDate('scheduled_at', today())->count(),
                'documenti_da_firmare' => Document::where('status', Document::STATUS_DA_FIRMARE)->count(),
                'farmaci_in_esaurimento' => Medicine::where('active', true)->lowStock()->count(),
                'fatturato_mese'     => round((float) ($mese->fatturato ?? 0), 2),
                'incassato_mese'     => round((float) ($mese->incassato ?? 0), 2),
                // Scoperto reale: si abbassa anche dopo un acconto parziale
                'da_incassare'       => Invoice::outstanding(),
                'fatture_da_saldare' => Invoice::unpaid()->count(),
            ],
            'appuntamenti_oggi' => AppointmentResource::collection(
                Appointment::with(['patient.user', 'doctor.user'])
                    ->whereDate('scheduled_at', today())
                    ->orderBy('scheduled_at')->limit(10)->get()
            ),
            'ultimi_utenti' => UserResource::collection(User::latest()->limit(5)->get()),
        ];
    }
}
