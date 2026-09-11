<?php

namespace App\Providers;

use App\Models\TelemedicineSession;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Il parametro di rotta {session} non corrisponde al nome del model: binding esplicito
        Route::model('session', TelemedicineSession::class);

        /*
         * Rate limiting delle API: 120 richieste al minuto per utente autenticato,
         * altrimenti per indirizzo IP.
         */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Troppe richieste. Riprova tra qualche istante.',
                ], 429));
        });

        /*
         * Login: qui c'e' solo la protezione grossolana contro il flood (chiunque
         * puo' bussare, ma non mille volte al secondo). La difesa vera dal brute
         * force sta in AuthController::login e conta i soli tentativi FALLITI per
         * coppia email+IP, azzerandoli al primo accesso riuscito.
         *
         * Motivo: 'throttle:6,1' contava anche i login andati a buon fine. Bastava
         * una manciata di accessi legittimi dallo stesso IP - la suite E2E, un
         * ufficio dietro NAT, un utente che cambia dispositivo - per bloccare tutti
         * con "Too Many Attempts", senza che nessuno stesse indovinando password.
         */
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) config('auth.login.flood_per_minute', 60))
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Troppe richieste di accesso. Riprova tra qualche istante.',
                ], 429));
        });

        // Dietro proxy/HTTPS gli URL generati (download, avatar) devono restare coerenti
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
