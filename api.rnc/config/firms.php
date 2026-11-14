<?php

/*
|--------------------------------------------------------------------------
| Firms (tenants)
|--------------------------------------------------------------------------
|
| The firm a request belongs to when nobody is signed in and the public site sends no `X-Firm`
| header (`ResolveCurrentFirm`) — the root host. Outside testing that is the platform firm `hub`
| (rnc-4 D3, D6); `phpunit.xml` pins `franco` so the suite keeps its implicit firm.
|
*/

return [

    'default_slug' => env('DEFAULT_FIRM_SLUG', 'hub'),

];
