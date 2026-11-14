<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the SPA lives
    |--------------------------------------------------------------------------
    |
    | The API sometimes has to send a person to the app — a share link is read by
    | a crawler here and by a human there. Read through config and never through
    | `env()` at the call site: `php artisan config:cache` runs on every deploy,
    | and after it `env()` outside a config file answers null.
    |
    | The file is named after the bootstrap on purpose: `specialize()` rewrites
    | the word, so each project receives it as `config/<project>.php` with the
    | call sites already pointing at the right key.
    |
    */

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    | Retired 14/11/2026 (rnc-4 F2a, R12): `brand` (`BRAND_NAME`, `BRAND_INK`, `BRAND_ACCENT`,
    | `BRAND_LOGO`) — one brand, Franco's, on every e-mail. Each message now wears the firm it
    | concerns (`App\Modules\Firm\Services\MailBrand`).
    */

];
