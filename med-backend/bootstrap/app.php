<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LogApiActivity;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   // rotte API versionate
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias usati nelle rotte: role:admin,medico  |  active  |  audit
        $middleware->alias([
            'role'   => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
            'audit'  => LogApiActivity::class,
        ]);

        // Il gruppo api e' stateless: nessuna sessione, nessun CSRF.
        // HandleCors e' gia' globale in Laravel 12: qui serve solo il rate limiting.
        $middleware->api(append: [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Formato di errore unico per tutta l'API.
         * Il frontend Vue puo' cosi' avere un solo interceptor che sa sempre
         * dove trovare "message" e "errors".
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // le rotte web mantengono il comportamento standard
            }

            return match (true) {
                $e instanceof ValidationException => response()->json([
                    'message' => 'I dati inviati non sono validi.',
                    'errors'  => $e->errors(),
                ], 422),

                $e instanceof AuthenticationException => response()->json([
                    'message' => 'Non autenticato.',
                ], 401),

                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => response()->json([
                    'message' => 'Risorsa non trovata.',
                ], 404),

                $e instanceof HttpExceptionInterface => response()->json([
                    'message' => $e->getMessage() ?: 'Errore nella richiesta.',
                ], $e->getStatusCode()),

                default => response()->json([
                    'message' => config('app.debug') ? $e->getMessage() : 'Errore interno del server.',
                    'debug'   => config('app.debug') ? [
                        'exception' => $e::class,
                        'file'      => $e->getFile(),
                        'line'      => $e->getLine(),
                    ] : null,
                ], 500),
            };
        });
    })->create();
