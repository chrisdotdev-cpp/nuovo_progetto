<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RespondPatientRequestRequest;
use App\Http\Requests\StorePatientRequestRequest;
use App\Http\Resources\PatientRequestResource;
use App\Models\PatientRequest;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Richieste asincrone paziente -> medico.
 * Il medico risponde, converte in appuntamento oppure chiude con prescrizione.
 */
class PatientRequestController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AppointmentService $appointments,
    ) {
        $this->authorizeResource(PatientRequest::class, 'request');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $requests = PatientRequest::query()
            // 'appointment' e' caricata in eager loading: senza, la data mostrata in
            // lista costerebbe una query per riga (N+1)
            ->with(['patient.user', 'doctor.user', 'attachments', 'appointment'])
            ->when($user->isPatient(), fn ($q) => $q->where('patient_id', $user->patient?->id))
            // Il medico vede le sue richieste piu' quelle ancora in coda generale
            ->when($user->isDoctor(), fn ($q) => $q->where(
                fn ($sub) => $sub->where('doctor_id', $user->doctor?->id)->orWhereNull('doctor_id')
            ))
            ->status($request->query('status'))
            ->priority($request->query('priority'))
            ->triageOrder()
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return PatientRequestResource::collection($requests);
    }

    public function store(StorePatientRequestRequest $request): JsonResponse
    {
        $patientRequest = DB::transaction(function () use ($request) {
            $patientRequest = PatientRequest::create([
                'patient_id'  => $this->patientIdOf($request->user()),
                'doctor_id'   => $request->input('doctor_id'),
                'subject'     => $request->subject,
                'description' => $request->description,
                'priority'    => $request->priority,
                'status'      => 'aperta',
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $patientRequest->attachments()->create([
                    'file_path'     => $file->store("richieste/{$patientRequest->id}", 'local'),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }

            return $patientRequest;
        });

        // Avviso al medico destinatario, se indicato
        if ($patientRequest->doctor_id) {
            $this->notifications->push(
                $patientRequest->doctor->user_id,
                'Nuova richiesta paziente',
                $patientRequest->subject,
                'messaggio',
                $patientRequest->priority === 'alta' ? 'attenzione' : 'info',
                '/medico/richieste'
            );
        }

        return response()->json([
            'message' => 'Richiesta inviata.',
            'data'    => new PatientRequestResource($patientRequest->load(['attachments', 'doctor.user'])),
        ], 201);
    }

    public function show(PatientRequest $request): PatientRequestResource
    {
        return new PatientRequestResource($request->load(['patient.user', 'doctor.user', 'attachments', 'appointment']));
    }

    public function update(Request $httpRequest, PatientRequest $request): JsonResponse
    {
        $data = $httpRequest->validate([
            'subject'     => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'min:10'],
            'priority'    => ['sometimes', 'in:bassa,media,alta'],
        ]);

        $request->update($data);

        return response()->json([
            'message' => 'Richiesta aggiornata.',
            'data'    => new PatientRequestResource($request->fresh(['attachments'])),
        ]);
    }

    public function destroy(PatientRequest $request): JsonResponse
    {
        $request->delete();

        return response()->json(['message' => 'Richiesta eliminata.']);
    }

    /** Presa in carico da parte del medico. */
    public function claim(Request $httpRequest, PatientRequest $request): JsonResponse
    {
        $this->authorize('respond', $request);

        $request->update([
            'doctor_id' => $this->doctorIdOf($httpRequest->user()),
            'status'    => 'in_carico',
        ]);

        return response()->json([
            'message' => 'Richiesta presa in carico.',
            'data'    => new PatientRequestResource($request->fresh(['patient.user', 'doctor.user', 'appointment'])),
        ]);
    }

    /** Risposta scritta del medico. */
    public function respond(RespondPatientRequestRequest $httpRequest, PatientRequest $request): JsonResponse
    {
        $this->authorize('respond', $request);

        $request->update([
            'doctor_id'    => $request->doctor_id ?? $this->doctorIdOf($httpRequest->user()),
            'response'     => $httpRequest->response,
            'responded_at' => now(),
            'status'       => $httpRequest->input('status', 'risposta'),
        ]);

        $this->notifications->push(
            $request->patient->user_id,
            'Risposta dal medico',
            \Illuminate\Support\Str::limit($httpRequest->response, 120),
            'messaggio',
            'successo',
            '/paziente/notifiche'
        );

        return response()->json([
            'message' => 'Risposta inviata.',
            'data'    => new PatientRequestResource($request->fresh(['patient.user', 'doctor.user', 'appointment'])),
        ]);
    }

    /** Conversione della richiesta in appuntamento di telemedicina o visita. */
    public function convertToAppointment(Request $httpRequest, PatientRequest $request): JsonResponse
    {
        $this->authorize('respond', $request);

        $data = $httpRequest->validate([
            'scheduled_at'     => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'type'             => ['nullable', 'in:visita,controllo,telemedicina,urgenza'],
        ]);

        $appointment = $this->appointments->book([
            ...$data,
            'patient_id' => $request->patient_id,
            'doctor_id'  => $this->doctorIdOf($httpRequest->user()),
            'type'       => $data['type'] ?? 'telemedicina',
            'reason'     => $request->subject,
            'status'     => 'confermato',
        ]);

        $request->update([
            'appointment_id' => $appointment->id,
            'doctor_id'      => $this->doctorIdOf($httpRequest->user()),
            'status'         => 'chiusa',
        ]);

        return response()->json([
            'message'     => 'Richiesta convertita in appuntamento.',
            'appointment' => new \App\Http\Resources\AppointmentResource($appointment),
            'data'        => new PatientRequestResource($request->fresh(['patient.user', 'doctor.user', 'appointment'])),
        ], 201);
    }

    public function downloadAttachment(int $attachmentId): StreamedResponse
    {
        $attachment = \App\Models\RequestAttachment::with('request')->findOrFail($attachmentId);

        $this->authorize('view', $attachment->request);

        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404, 'File non disponibile.');

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
