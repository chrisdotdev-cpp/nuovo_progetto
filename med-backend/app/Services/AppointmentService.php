<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logica di disponibilita' e prenotazione.
 *
 * Tutta la matematica sugli slot vive qui: controller e frontend
 * non devono mai ricalcolarla.
 */
class AppointmentService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Slot liberi di un medico in una data.
     *
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    public function availableSlots(Doctor $doctor, string $date, ?int $ignoreAppointmentId = null): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        // 1. Il medico e' in ferie quel giorno?
        $isAbsent = $doctor->absences()
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->exists();

        if ($isAbsent) {
            return [];
        }

        // 2. Fasce orarie previste per quel giorno della settimana (ISO: 1=lun ... 7=dom)
        $schedules = $doctor->schedules()
            ->where('weekday', $day->dayOfWeekIso)
            ->where('active', true)
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        // 3. Appuntamenti gia' presi in quella giornata
        $booked = $doctor->appointments()
            ->whereDate('scheduled_at', $day)
            ->where('status', '!=', Appointment::STATUS_ANNULLATO)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->get(['scheduled_at', 'duration_minutes']);

        $slotMinutes = max(5, (int) $doctor->slot_duration);
        $slots       = [];

        foreach ($schedules as $schedule) {
            $cursor = $day->setTimeFromTimeString($schedule->start_time);
            $end    = $day->setTimeFromTimeString($schedule->end_time);

            while ($cursor->addMinutes($slotMinutes)->lessThanOrEqualTo($end)) {
                $slotStart = $cursor;
                $slotEnd   = $cursor->addMinutes($slotMinutes);

                // Uno slot nel passato non e' prenotabile
                $isPast = $slotStart->isPast();

                $overlaps = $booked->contains(function ($appointment) use ($slotStart, $slotEnd) {
                    $bookedStart = CarbonImmutable::parse($appointment->scheduled_at);
                    $bookedEnd   = $bookedStart->addMinutes($appointment->duration_minutes);

                    return $slotStart->lessThan($bookedEnd) && $slotEnd->greaterThan($bookedStart);
                });

                $slots[] = [
                    'start'     => $slotStart->toIso8601String(),
                    'end'       => $slotEnd->toIso8601String(),
                    'label'     => $slotStart->format('H:i'),
                    'available' => ! $overlaps && ! $isPast,
                ];

                $cursor = $slotEnd;
            }
        }

        return $slots;
    }

    /**
     * Crea un appuntamento verificando la sovrapposizione in transazione,
     * cosi' due prenotazioni simultanee non possono occupare lo stesso slot.
     */
    public function book(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $doctor   = Doctor::lockForUpdate()->findOrFail($data['doctor_id']);
            $start    = CarbonImmutable::parse($data['scheduled_at']);
            $duration = (int) ($data['duration_minutes'] ?? $doctor->slot_duration);

            $this->assertSlotIsFree($doctor, $start, $duration);

            $appointment = Appointment::create([
                'patient_id'       => $data['patient_id'],
                'doctor_id'        => $doctor->id,
                'scheduled_at'     => $start,
                'duration_minutes' => $duration,
                'type'             => $data['type'] ?? 'visita',
                'status'           => $data['status'] ?? Appointment::STATUS_IN_ATTESA,
                'reason'           => $data['reason'] ?? null,
                'notes'            => $data['notes'] ?? null,
            ]);

            // Se e' un teleconsulto si predispone subito la stanza virtuale
            if ($appointment->type === 'telemedicina') {
                app(TelemedicineService::class)->createSessionFor($appointment);
            }

            $this->notifications->appointmentCreated($appointment);

            return $appointment->load(['patient.user', 'doctor.user']);
        });
    }

    /** Spostamento di un appuntamento esistente. */
    public function reschedule(Appointment $appointment, string $scheduledAt, ?int $duration = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $scheduledAt, $duration) {
            $start    = CarbonImmutable::parse($scheduledAt);
            $duration = $duration ?? $appointment->duration_minutes;

            $this->assertSlotIsFree($appointment->doctor, $start, $duration, $appointment->id);

            $appointment->update([
                'scheduled_at'     => $start,
                'duration_minutes' => $duration,
                'status'           => Appointment::STATUS_IN_ATTESA,
                'confirmed_at'     => null,
            ]);

            $this->notifications->appointmentRescheduled($appointment);

            return $appointment->fresh(['patient.user', 'doctor.user']);
        });
    }

    public function cancel(Appointment $appointment, int $cancelledBy, ?string $reason = null): Appointment
    {
        $appointment->update([
            'status'              => Appointment::STATUS_ANNULLATO,
            'cancellation_reason' => $reason,
            'cancelled_by'        => $cancelledBy,
        ]);

        $appointment->telemedicineSession?->update(['status' => 'annullata']);

        $this->notifications->appointmentCancelled($appointment);

        return $appointment->fresh(['patient.user', 'doctor.user']);
    }

    /**
     * Verifica che lo slot richiesto rientri nell'orario di lavoro e sia libero.
     *
     * @throws ValidationException
     */
    private function assertSlotIsFree(Doctor $doctor, CarbonImmutable $start, int $duration, ?int $ignoreId = null): void
    {
        $end = $start->addMinutes($duration);

        if ($start->isPast()) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Non e\' possibile prenotare in una data passata.',
            ]);
        }

        // Il medico e' in ferie?
        $isAbsent = $doctor->absences()
            ->whereDate('start_date', '<=', $start)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($isAbsent) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Il medico non e\' disponibile in questa data.',
            ]);
        }

        // Lo slot cade dentro una fascia di lavoro?
        $insideSchedule = $doctor->schedules()
            ->where('weekday', $start->dayOfWeekIso)
            ->where('active', true)
            ->where('start_time', '<=', $start->format('H:i:s'))
            ->where('end_time', '>=', $end->format('H:i:s'))
            ->exists();

        if (! $insideSchedule) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'L\'orario richiesto e\' fuori dall\'agenda del medico.',
            ]);
        }

        // Sovrapposizione con un altro appuntamento
        $overlapping = $doctor->appointments()
            ->where('status', '!=', Appointment::STATUS_ANNULLATO)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereDate('scheduled_at', $start->toDateString())
            ->get(['id', 'scheduled_at', 'duration_minutes'])
            ->contains(function ($appointment) use ($start, $end) {
                $bookedStart = CarbonImmutable::parse($appointment->scheduled_at);
                $bookedEnd   = $bookedStart->addMinutes($appointment->duration_minutes);

                return $start->lessThan($bookedEnd) && $end->greaterThan($bookedStart);
            });

        if ($overlapping) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Lo slot selezionato non e\' piu\' disponibile.',
            ]);
        }
    }

    /** Prossimi appuntamenti di un paziente, usato dalla dashboard. */
    public function upcomingForPatient(int $patientId, int $limit = 5): Collection
    {
        return Appointment::with(['doctor.user'])
            ->where('patient_id', $patientId)
            ->upcoming()
            ->limit($limit)
            ->get();
    }
}
