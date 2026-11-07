<?php

/*
|--------------------------------------------------------------------------
| Firms (tenants)
|--------------------------------------------------------------------------
|
| The firm a request belongs to when nobody is signed in and the public site sends no `X-Firm`
| header (`ResolveCurrentFirm`). Franco Contabilidade is the pilot's only firm.
|
*/

return [

    'default_slug' => env('DEFAULT_FIRM_SLUG', 'franco'),

];
