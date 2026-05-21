<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| PB-5 / INFRA-03: previously wildcards (`*`) on origins/headers/methods
| with supports_credentials=true — spec-illegal combination that browsers
| reject today but invited any future library/regex change to flip into
| a real cross-origin credentialed surface. Now driven by env.
|
*/

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', env('CORS_ALLOWED_ORIGINS', ''))
)));

if (empty($allowedOrigins)) {
    // Sensible defaults: same-origin app URL + the loopback ports used
    // by Vite/dev tooling. Override in production via CORS_ALLOWED_ORIGINS.
    $appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
    $allowedOrigins = array_filter([
        $appUrl,
        'http://localhost',
        'http://localhost:5173',
        'http://localhost:8000',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
    ]);
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-CSRF-TOKEN',
        'X-Requested-With',
        'Mcp-Session-Id',
    ],

    'exposed_headers' => ['Mcp-Session-Id'],

    'max_age' => 600,

    'supports_credentials' => true,
];
