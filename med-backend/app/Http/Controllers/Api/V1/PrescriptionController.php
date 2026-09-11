<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Requests\UpdatePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\PrescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $service)
    {
        $this->authorizeResource(Prescription::class, 'prescription');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $prescriptions = Prescription::query()
            ->with(['items.medicine', 'patient.user', 'doctor.user'])
            ->withCount('items')
            ->when($user->isPatient(), fn ($q) => $q->where('patient_id', $user->patient?->id))
            ->when($user->isDoctor(), fn ($q) => $q->where('doctor_id', $user->doctor?->id))
            ->when($request->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->status($request->query('status'))
            ->when($request->query('q'), fn ($q, $term) => $q->where('code', 'like', "%{$term}%"))
            ->latest('issued_at')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return PrescriptionResource::collection($prescriptions);
    }

    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        // Il medico deve avere accesso alla scheda del paziente
        $this->authorize('view', Patient::findOrFail($request->patient_id));

        $prescription = $this->service->create($request->validated(), $this->doctorIdOf($request->user()));

        return response()->json([
            'message' => 'Prescrizione emessa.',
            'data'    => new PrescriptionResource($prescription),
        ], 201);
    }

    public function show(Prescription $prescription): PrescriptionResource
    {
        return new PrescriptionResource($prescription->load(['items.medicine', 'patient.user', 'doctor.user']));
    }

    public function update(UpdatePrescriptionRequest $request, Prescription $prescription): JsonResponse
    {
        return response()->json([
            'message' => 'Prescrizione aggiornata.',
            'data'    => new PrescriptionResource($this->service->update($prescription, $request->validated())),
        ]);
    }

    public function destroy(Prescription $prescription): JsonResponse
    {
        $prescription->delete();

        return response()->json(['message' => 'Prescrizione eliminata.']);
    }
}
