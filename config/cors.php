<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    | Documenta explícitamente el comportamiento que ya ocurre hoy de facto (permisivo).
    | El cliente móvil (NativePHP) no es un navegador y no depende de CORS: hace peticiones
    | servidor-a-servidor con Bearer token, no fetch()/XHR desde un origen web. Si en el
    | futuro se agrega un cliente basado en navegador, restringir `allowed_origins` a los
    | dominios reales en producción.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
