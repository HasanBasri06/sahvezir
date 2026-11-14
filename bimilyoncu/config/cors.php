<?php

/*
| CORS: sadece bilinen client origin'leri API'ye tarayıcıdan erişebilir.
| .env içinde CORS_ALLOWED_ORIGINS (virgülle ayrılmış) ile ezilebilir.
| Auth Bearer token ile yapıldığı için credentials (cookie) kapalı.
*/

$defaultOrigins = implode(',', [
    // Local
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    // Canlı
    'https://bimilyoncu.com',
    'https://www.bimilyoncu.com',
]);

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', $defaultOrigins))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
