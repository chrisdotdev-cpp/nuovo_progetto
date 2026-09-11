<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DoctorController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments)
    {
        $this->authorizeResource(Doctor::class, 'doctor');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $doctors = Doctor::query()
            ->with('user')
            /*
             * Un profilo medico senza account valido (utente cancellato o mai
             * creato) e' un record orfano: comparirebbe in lista senza nome e
             * non sarebbe prenotabile. Si esclude alla fonte.
             */
            ->whereHas('user')
            ->specialization($request->query('specialization'))
            ->when($request->boolean('online_only'), fn ($q) => $q->onlineEnabled())
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->search($term)))
            // Ordine stabile: senza questo la paginazione MySQL puo' ripetere righe
            ->orderBy('specialization')
            ->orderBy('id')
            ->paginate((int) $request->query('per_page', 50))
            ->withQueryString();

        return DoctorResource::collection($doctors);
    }

    public function store(StoreDoctorRequest $request): JsonResponse
    {
        $doctor = Doctor::create($request->validated());

        return response()->json([
            'message' => 'Medico creato.',
            'data'    => new DoctorResource($doctor->load('user')),
        ], 201);
    }

    public function show(Doctor $doctor): DoctorResource
    {
        return new DoctorResource($doctor->load(['user', 'schedules']));
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): JsonResponse
    {
        DB::transaction(function () use ($request, $doctor) {
            $doctor->update($request->safe()->except('schedules'));

            // L'orario settimanale si sostituisce in blocco
            if ($request->has('schedules')) {
                $this->authorize('manageSchedule', $doctor);
                $doctor->schedules()->delete();
                $doctor->schedules()->createMany($request->input('schedules'));
            }
        });

        return response()->json([
            'message' => 'Profilo medico aggiornato.',
            'data'    => new DoctorResource($doctor->fresh(['user', 'schedules'])),
        ]);
    }

    public function destroy(Doctor $doctor): JsonResponse
    {
        $doctor->delete();

        return response()->json(['message' => 'Medico archiviato.']);
    }

    /**
     * Slot prenotabili di un medico in una data: alimenta il calendario Vue.
     * GET /doctors/{doctor}/availability?date=2026-08-03
     */
    public function availability(Request $request, Doctor $doctor): JsonResponse
    {
        $request->validate(['date' => ['required', 'date']]);

        return response()->json([
            'date'   => $request->query('date'),
            'doctor' => new DoctorResource($doctor->load('user')),
            'slots'  => $this->appointments->availableSlots($doctor, $request->query('date')),
        ]);
    }

    /** Elenco delle specializzazioni presenti: popola i filtri senza hardcodarli in Vue. */
    public function specializations(): JsonResponse
    {
        return response()->json([
            // Stessi criteri di index(): il filtro non deve proporre valori
            // che poi non restituiscono nessun medico.
            'data' => Doctor::query()
                ->whereHas('user')
                ->whereNotNull('specialization')
                ->distinct()
                ->orderBy('specialization')
                ->pluck('specialization'),
        ]);
    }
}
