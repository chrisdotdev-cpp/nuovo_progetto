<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PatientController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Patient::class, 'patient');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $patients = Patient::query()
            ->with(['user', 'primaryDoctor.user'])
            /*
             * Il medico vede solo i propri assistiti, l'admin tutti.
             *
             * L'operatore ?-> e' necessario: un account con role='medico' ma
             * senza record in `doctors` faceva fallire la pagina con un 500
             * ("Attempt to read property id on null"). Con doctor_id NULL la
             * clausola non trova nulla e la lista resta vuota, che e' il
             * comportamento corretto per un profilo non ancora configurato.
             */
            ->when($user->isDoctor(), fn ($q) => $q->ofDoctor($user->doctor?->id))
            ->search($request->query('q'))
            ->when($request->query('city'), fn ($q, $city) => $q->where('city', $city))
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = Patient::create($request->validated());

        return response()->json([
            'message' => 'Paziente creato.',
            'data'    => new PatientResource($patient->load('user')),
        ], 201);
    }

    public function show(Patient $patient): PatientResource
    {
        return new PatientResource($patient->load(['user', 'primaryDoctor.user']));
    }

    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $data = $request->validated();

        // Il paziente non puo' assegnarsi da solo un medico di base
        if ($request->user()->isPatient()) {
            unset($data['primary_doctor_id']);
        }

        $patient->update($data);

        return response()->json([
            'message' => 'Anagrafica aggiornata.',
            'data'    => new PatientResource($patient->fresh(['user', 'primaryDoctor.user'])),
        ]);
    }

    public function destroy(Patient $patient): JsonResponse
    {
        $patient->delete();

        return response()->json(['message' => 'Paziente archiviato.']);
    }

    /** Scheda del paziente autenticato: evita al frontend di conoscere il proprio patient_id. */
    public function me(Request $request): PatientResource
    {
        $patient = $request->user()->patient()->with(['user', 'primaryDoctor.user'])->first();

        /*
         * firstOrFail() rispondeva con un 404 generico, indistinguibile da una
         * rotta sbagliata. Se l'account esiste in `users` ma non ha un record in
         * `patients` il problema e' di DATI, non di routing.
         *
         * Si usa 409 e non 404 di proposito: l'handler in bootstrap/app.php
         * intercetta NotFoundHttpException e sostituisce il messaggio con
         * "Risorsa non trovata.", buttando via la spiegazione. Il ramo
         * HttpExceptionInterface invece preserva getMessage(), quindi il testo
         * arriva intatto al toast di Vue. 409 Conflict e' anche piu' corretto:
         * la risorsa e' prevista dal modello ma lo stato dei dati e' incoerente.
         */
        abort_if($patient === null, 409, "Profilo paziente non trovato per l'account "
            .$request->user()->email.'. Il record in `patients` non e\' stato creato: '
            .'esegui `php artisan med:diagnosi --fix` oppure lo script sincronizza_profili.sql.');

        return new PatientResource($patient);
    }
}
