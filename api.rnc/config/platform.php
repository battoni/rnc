<?php

/*
|--------------------------------------------------------------------------
| The platform (rnc-4)
|--------------------------------------------------------------------------
|
| The product every firm runs on. A firm lives at `<slug>.<root_domain>`; the root and
| `www.<root_domain>` serve the platform firm (`is_platform`, slug `hub`, D3). A reserved slug is
| never a firm's address: `X-Firm` naming one is a 404 (D2), and `hub` is reserved so the
| platform firm is reachable only header-less, at the root (plan AM9).
|
*/

return [

    'name' => env('PLATFORM_NAME', 'Hub da Reforma'),

    'root_domain' => env('PLATFORM_ROOT_DOMAIN', 'localhost'),

    'reserved_slugs' => [
        'www', 'api', 'app', 'admin', 'hub', 'mail', 'static', 'assets', 'docs', 'status', 'beta', 'demo', 'suporte',
    ],

];
