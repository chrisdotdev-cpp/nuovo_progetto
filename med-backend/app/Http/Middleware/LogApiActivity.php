<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit trail: registra le operazioni di scrittura e gli accessi ai dati clinici.
 * Applicato solo dove serve, per non gonfiare la tabella con ogni GET.
 */
class LogApiActivity
{
    public function handle(Request $request, Closure $next, string $action = 'api_request'): Response
    {
        $response = $next($request);

        // Si registra solo se la richiesta e' andata a buon fine
        if ($request->user() && $response->getStatusCode() < 400) {
            ActivityLog::create([
                'user_id'    => $request->user()->id,
                'action'     => $action,
                'properties' => [
                    'method' => $request->method(),
                    'path'   => $request->path(),
                    'params' => $request->route()?->parameters(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return $response;
    }
}
