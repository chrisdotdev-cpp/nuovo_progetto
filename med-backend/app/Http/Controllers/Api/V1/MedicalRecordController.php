<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Cartella clinica. */
class MedicalRecordController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MedicalRecord::class, 'medical_record');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $records = MedicalRecord::query()
            ->with(['doctor.user', 'patient.user'])
            ->when($user->isPatient(), fn ($q) => $q->where('patient_id', $user->patient?->id))
            ->when($request->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->type($request->query('type'))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('recorded_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('recorded_at', '<=', $to))
            ->orderByDesc('recorded_at')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return MedicalRecordResource::collection($records);
    }

    public function store(StoreMedicalRecordRequest $request): JsonResponse
    {
        // Il medico non puo' scrivere nella cartella di un paziente che non segue
        $patient = Patient::findOrFail($request->patient_id);
        $this->authorize('view', $patient);

        $record = MedicalRecord::create([
            ...$request->validated(),
            'doctor_id'   => $this->doctorIdOf($request->user()),
            'recorded_at' => $request->input('recorded_at', now()),
        ]);

        return response()->json([
            'message' => 'Voce clinica registrata.',
            'data'    => new MedicalRecordResource($record->load('doctor.user')),
        ], 201);
    }

    public function show(MedicalRecord $medicalRecord): MedicalRecordResource
    {
        return new MedicalRecordResource($medicalRecord->load(['doctor.user', 'patient.user']));
    }

    public function update(UpdateMedicalRecordRequest $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $medicalRecord->update($request->validated());

        return response()->json([
            'message' => 'Voce clinica aggiornata.',
            'data'    => new MedicalRecordResource($medicalRecord->fresh('doctor.user')),
        ]);
    }

    public function destroy(MedicalRecord $medicalRecord): JsonResponse
    {
        $medicalRecord->delete();

        return response()->json(['message' => 'Voce clinica rimossa.']);
    }

    /** Timeline completa di un paziente: cartella + prescrizioni + documenti. */
    public function timeline(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        return response()->json([
            'patient' => new \App\Http\Resources\PatientResource($patient->load('user')),
            'records' => MedicalRecordResource::collection(
                $patient->medicalRecords()->with('doctor.user')->limit(100)->get()
            ),
            'prescriptions' => \App\Http\Resources\PrescriptionResource::collection(
                $patient->prescriptions()->with(['items', 'doctor.user'])->latest('issued_at')->limit(50)->get()
            ),
            'documents' => \App\Http\Resources\DocumentResource::collection(
                $patient->documents()->latest()->limit(50)->get()
            ),
        ]);
    }
}
