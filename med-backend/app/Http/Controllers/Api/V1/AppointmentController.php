<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $service)
    {
        $this->authorizeResource(Appointment::class, 'appointment');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $appointments = Appointment::query()
            ->with(['patient.user', 'doctor.user', 'telemedicineSession'])
            // Ogni ruolo vede solo la propria fetta di agenda
            ->when($user->isPatient(), fn ($q) => $q->where('patient_id', $user->patient?->id))
            ->when($user->isDoctor(),  fn ($q) => $q->where('doctor_id', $user->doctor?->id))
            ->when($request->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when($request->query('doctor_id'), fn ($q, $id) => $q->where('doctor_id', $id))
            ->status($request->query('status'))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('from') && $request->query('to'),
                fn ($q) => $q->between($request->query('from'), $request->query('to')))
            ->when($request->query('scope') === 'upcoming', fn ($q) => $q->upcoming())
            ->when($request->query('scope') === 'past', fn ($q) => $q->past())
            ->when(! $request->query('scope'), fn ($q) => $q->orderByDesc('scheduled_at'))
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        // Tutta la logica di slot e concorrenza sta nel service
        $appointment = $this->service->book($request->validated());

        return response()->json([
            'message' => 'Appuntamento prenotato.',
            'data'    => new AppointmentResource($appointment),
        ], 201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource(
            $appointment->load(['patient.user', 'doctor.user', 'telemedicineSession'])
        );
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validated();

        // Lo spostamento passa dal service per ricontrollare la disponibilita'
        if (isset($data['scheduled_at'])) {
            $appointment = $this->service->reschedule(
                $appointment,
                $data['scheduled_at'],
                $data['duration_minutes'] ?? null
            );
            unset($data['scheduled_at'], $data['duration_minutes']);
        }

        if (isset($data['status'])) {
            $this->authorize('changeStatus', $appointment);

            if ($data['status'] === Appointment::STATUS_CONFERMATO) {
                $data['confirmed_at'] = now();
            }
        }

        if ($data !== []) {
            $appointment->update($data);
        }

        return response()->json([
            'message' => 'Appuntamento aggiornato.',
            'data'    => new AppointmentResource($appointment->fresh(['patient.user', 'doctor.user'])),
        ]);
    }

    /** Annullamento: non cancella il record, ne cambia lo stato tracciando chi e perche'. */
    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('cancel', $appointment);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $appointment = $this->service->cancel($appointment, $request->user()->id, $request->input('reason'));

        return response()->json([
            'message' => 'Appuntamento annullato.',
            'data'    => new AppointmentResource($appointment),
        ]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(['message' => 'Appuntamento eliminato.']);
    }
}
