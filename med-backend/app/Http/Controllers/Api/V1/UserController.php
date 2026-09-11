<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Gestione account (area amministrazione).
 * Alla creazione genera anche il profilo Patient o Doctor collegato.
 */
class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['patient', 'doctor'])
            ->search($request->query('q'))
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy($request->query('sort', 'created_at'), $request->query('direction', 'desc'))
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->only(['name', 'email', 'password', 'role', 'phone', 'status']));

            // Il profilo collegato nasce insieme all'account
            if ($user->isDoctor()) {
                $user->doctor()->create([
                    'specialization'   => $request->specialization,
                    'license_number'   => $request->license_number,
                    'consultation_fee' => $request->consultation_fee ?? 0,
                    'slot_duration'    => $request->slot_duration ?? 30,
                ]);
            }

            if ($user->isPatient()) {
                $user->patient()->create([
                    'codice_fiscale' => $request->codice_fiscale,
                    'birth_date'     => $request->birth_date,
                    'gender'         => $request->gender,
                ]);
            }

            return $user;
        });

        return response()->json([
            'message' => 'Utente creato.',
            'data'    => new UserResource($user->load(['patient', 'doctor'])),
        ], 201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['patient.primaryDoctor.user', 'doctor.schedules']));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // Ruolo e stato restano privilegio dell'amministrazione
        if (! $request->user()->can('manageRole', User::class)) {
            unset($data['role'], $data['status']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Utente aggiornato.',
            'data'    => new UserResource($user->fresh(['patient', 'doctor'])),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->tokens()->delete(); // revoca l'accesso immediatamente
        $user->delete();           // soft delete: i dati clinici restano consultabili

        return response()->json(['message' => 'Utente disattivato.']);
    }
}
