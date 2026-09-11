<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocca gli account sospesi anche se possiedono un token ancora valido.
 * Il token viene revocato subito, cosi' il frontend viene espulso al primo 401.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            /*
             * Solo un token persistito si puo' revocare. Con Sanctum::actingAs()
             * (test) o con la guard di sessione currentAccessToken() restituisce
             * un TransientToken, che non espone delete(): chiamarlo trasformava
             * il 403 in un errore fatale 500.
             */
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'message' => 'Account sospeso. Contatta l\'amministrazione.',
            ], 403);
        }

        return $next($request);
    }
}
