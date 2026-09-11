<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filtra le rotte per ruolo applicativo.
 * Uso: ->middleware('role:admin')  oppure  ->middleware('role:admin,medico')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non autenticato.'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Non hai i permessi per questa operazione.',
            ], 403);
        }

        return $next($request);
    }
}
