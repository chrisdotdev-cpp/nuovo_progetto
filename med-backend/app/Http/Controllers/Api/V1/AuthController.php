<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Autenticazione via Sanctum in modalita' API token (Bearer).
 * Il frontend salva il token in Pinia (persisted) e lo invia in ogni richiesta.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $chiave = $this->chiaveTentativi($request);

        if ($bloccato = $this->rispostaSeBloccato($chiave)) {
            return $bloccato;
        }

        $user = User::where('email', $request->email)->first();

        // Messaggio volutamente generico: non si rivela se l'email esiste
        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->registraTentativoFallito($chiave);

            throw ValidationException::withMessages([
                'email' => 'Credenziali non valide.',
            ]);
        }

        if (! $user->isActive()) {
            $this->registraTentativoFallito($chiave);

            throw ValidationException::withMessages([
                'email' => 'Account non attivo. Contatta l\'amministrazione.',
            ]);
        }

        // Il ruolo scelto nella schermata di login deve corrispondere a quello reale
        if ($request->filled('role') && $request->role !== $user->role) {
            $this->registraTentativoFallito($chiave);

            throw ValidationException::withMessages([
                'email' => 'Questo account non e\' abilitato come '.$request->role.'.',
            ]);
        }

        /*
          Credenziali corrette: il contatore riparte da zero. E' la differenza
          fra "questo IP ha sbagliato cinque volte di fila" (sospetto) e "questo
          IP ha fatto cinque accessi riusciti" (un mercoledi' qualunque).
        */
        RateLimiter::clear($chiave);

        // Un token per dispositivo: il logout su mobile non butta fuori dal desktop
        $device = $request->input('device_name', 'web');
        $user->tokens()->where('name', $device)->delete();

        $token = $user->createToken($device, ['*'], now()->addMinutes(config('sanctum.expiration', 720)));

        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::create([
            'user_id'    => $user->id,
            'action'     => 'login',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'message'    => 'Accesso effettuato.',
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user'       => new UserResource($user->load(['patient', 'doctor'])),
            // Rotta di destinazione decisa dal backend: unico punto di verita' sui ruoli
            'redirect'   => $this->dashboardFor($user),
        ]);
    }

    /**
     * Chiave del contatore: email + IP.
     *
     * Solo l'IP bloccherebbe un intero ufficio dietro NAT per colpa di un
     * collega distratto; solo l'email permetterebbe a chiunque di bloccare a
     * comando l'account altrui sbagliando la password apposta.
     */
    private function chiaveTentativi(LoginRequest $request): string
    {
        return 'login:'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    /** 429 con i secondi di attesa, oppure null se si puo' procedere. */
    private function rispostaSeBloccato(string $chiave): ?JsonResponse
    {
        $massimo = (int) config('auth.login.max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($chiave, $massimo)) {
            return null;
        }

        $secondi = RateLimiter::availableIn($chiave);

        $messaggio = 'Troppi tentativi di accesso falliti. Riprova fra '
            .($secondi > 60 ? ceil($secondi / 60).' minuti.' : $secondi.' secondi.');

        // 'errors.email' oltre a 'message': il frontend mostra il testo nel box
        // sopra al form, dove l'utente sta gia' guardando.
        return response()->json([
            'message' => $messaggio,
            'errors'  => ['email' => [$messaggio]],
        ], 429, ['Retry-After' => $secondi]);
    }

    private function registraTentativoFallito(string $chiave): void
    {
        RateLimiter::hit($chiave, (int) config('auth.login.decay_seconds', 60));
    }

    /** Utente corrente: usato all'avvio dell'app per ripristinare la sessione. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['patient.primaryDoctor.user', 'doctor.schedules']);

        return response()->json([
            'user'     => new UserResource($user),
            'redirect' => $this->dashboardFor($user),
        ]);
    }

    /** Rinnova il token mantenendo la sessione viva senza chiedere di nuovo la password. */
    public function refresh(Request $request): JsonResponse
    {
        $user     = $request->user();
        $corrente = $this->tokenCorrente($request);
        $device   = $corrente?->name ?? 'web';

        $corrente?->delete();

        $token = $user->createToken($device, ['*'], now()->addMinutes(config('sanctum.expiration', 720)));

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokenCorrente($request)?->delete();

        return response()->json(['message' => 'Logout effettuato.']);
    }

    /** Revoca tutti i token: utile dopo un cambio password o un furto di dispositivo. */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Disconnesso da tutti i dispositivi.']);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'local');
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profilo aggiornato.',
            'user'    => new UserResource($user->fresh(['patient', 'doctor'])),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->password]);

        // Password cambiata: si invalidano gli altri token per sicurezza
        $currentId = $this->tokenCorrente($request)?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return response()->json(['message' => 'Password aggiornata.']);
    }

    /**
     * Token Sanctum persistito della richiesta corrente, se esiste.
     *
     * currentAccessToken() puo' restituire un TransientToken (guard di sessione,
     * Sanctum::actingAs nei test): quello non e' un model Eloquent e non espone
     * ne' name, ne' id, ne' delete(). Accedervi direttamente faceva fallire con
     * un 500 logout, refresh e cambio password. Qui il caso viene isolato.
     */
    private function tokenCorrente(Request $request): ?PersonalAccessToken
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    /** Mappa ruolo -> rotta frontend. Deve restare allineata al router Vue. */
    private function dashboardFor(User $user): string
    {
        return match ($user->role) {
            User::ROLE_ADMIN    => '/admin/panoramica',
            User::ROLE_MEDICO   => '/medico/panoramica',
            default             => '/paziente/panoramica',
        };
    }
}
