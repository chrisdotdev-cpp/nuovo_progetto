<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TelemedicineSessionResource;
use App\Models\Appointment;
use App\Models\TelemedicineSession;
use App\Services\TelemedicineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TelemedicineController extends Controller
{
    public function __construct(private readonly TelemedicineService $telemedicine)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $sessions = TelemedicineSession::query()
            ->with(['appointment.patient.user', 'appointment.doctor.user'])
            ->whereHas('appointment', function ($q) use ($user) {
                if ($user->isPatient()) {
                    $q->where('patient_id', $user->patient?->id);
                } elseif ($user->isDoctor()) {
                    $q->where('doctor_id', $user->doctor?->id);
                }
            })
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return TelemedicineSessionResource::collection($sessions);
    }

    public function show(TelemedicineSession $session): TelemedicineSessionResource
    {
        $this->authorize('view', $session);

        return new TelemedicineSessionResource(
            $session->load(['appointment.patient.user', 'appointment.doctor.user'])
        );
    }

    /** Crea la stanza per un appuntamento gia' esistente. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['appointment_id' => ['required', 'exists:appointments,id']]);

        $appointment = Appointment::findOrFail($data['appointment_id']);
        $this->authorize('update', $appointment);

        return response()->json([
            'message' => 'Sessione creata.',
            'data'    => new TelemedicineSessionResource($this->telemedicine->createSessionFor($appointment)),
        ], 201);
    }

    /**
     * Ingresso in stanza. Restituisce il codice solo se l'orario e' quello giusto:
     * il controllo temporale sta nella policy, non nel frontend.
     */
    public function join(TelemedicineSession $session): JsonResponse
    {
        $this->authorize('join', $session);

        return response()->json([
            'room_code' => $session->room_code,
            'data'      => new TelemedicineSessionResource($session->load('appointment.patient.user', 'appointment.doctor.user')),
        ]);
    }

    public function start(TelemedicineSession $session): JsonResponse
    {
        $this->authorize('manage', $session);

        return response()->json([
            'message' => 'Sessione avviata.',
            'data'    => new TelemedicineSessionResource($this->telemedicine->start($session)),
        ]);
    }

    public function end(Request $request, TelemedicineSession $session): JsonResponse
    {
        $this->authorize('manage', $session);

        $request->validate(['notes' => ['nullable', 'string']]);

        return response()->json([
            'message' => 'Sessione conclusa.',
            'data'    => new TelemedicineSessionResource($this->telemedicine->end($session, $request->input('notes'))),
        ]);
    }

    /* -----------------------------------------------------------------
     | Chat della sessione
     * ---------------------------------------------------------------*/

    public function messages(TelemedicineSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return response()->json([
            'data' => $session->messages()->with('user:id,name,role')->get(),
        ]);
    }

    public function sendMessage(Request $request, TelemedicineSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = $session->messages()->create([
            'user_id' => $request->user()->id,
            'body'    => $data['body'],
        ]);

        return response()->json(['data' => $message->load('user:id,name,role')], 201);
    }
}
