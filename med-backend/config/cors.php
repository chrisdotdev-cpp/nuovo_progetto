<?php

/*
 | CORS: il frontend Vue gira su un'origine diversa (Vite su :5173).
 | In modalita' token non servono cookie, quindi supports_credentials resta false.
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,capacitor://localhost,http://localhost'
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
