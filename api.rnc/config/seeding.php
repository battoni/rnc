<?php

/*
|--------------------------------------------------------------------------
| Seeded credentials
|--------------------------------------------------------------------------
|
| Read through config, never `env()` in a seeder or factory. Under a cached config `env()`
| returns null, and a seeder falls back to its guessable default in silence — on the server
| that re-seeds on every deploy, which is exactly where the default must never land.
|
*/

return [

    // The superadmin's password.
    'superadmin_password' => env('SUPERADMIN_PASSWORD', 'password'),

    // Every other seeded account (manager, the permission-less user) and the UserFactory default.
    'password' => env('SEEDER_PWD', 'password'),

];
