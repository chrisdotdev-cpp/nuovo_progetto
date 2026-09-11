<?php


return [

    /*
     | Modalita' scelta: API token (Bearer).
     | I domini stateful restano configurabili ma non sono usati dalla SPA,
     | che invia sempre l'header Authorization: Bearer <token>.
     */
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', '')),

    'guard' => ['web'],

    // Scadenza token in minuti: 12 ore. Il frontend rinnova con /auth/refresh.
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 720),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
