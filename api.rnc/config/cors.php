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
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * `http://localhost:5173` is where `npm run dev` and the Playwright suite serve app.rnc, and it is
     * already a Sanctum stateful domain. Leaving it out of the default answered every request from
     * that origin without CORS headers: the browser dropped them and the developer saw a blank
     * screen, with nothing in the API log.
     */
    'allowed_origins' => array_filter(explode(separator: ',', string: env(key: 'CORS_ALLOWED_ORIGINS', default: 'https://app.rnc.test,https://app.rnc.test:5173,http://localhost:5173'))),

    'allowed_origins_patterns' => array_filter(explode(separator: ',', string: env(key: 'CORS_ALLOWED_ORIGINS_PATTERNS', default: ''))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
