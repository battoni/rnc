<?php

/*
 * The Reforma assistant (rnc-3 R16, D9). `driver` names the `ReformaAnswerer` bound in
 * `AppServiceProvider::ASSISTANT_DRIVERS`; its only entry is `curated` — Franco's reviewed notes,
 * matched by words, nothing generated. An unknown name fails at boot instead of falling back.
 */
return [
    'driver' => env('ASSISTANT_DRIVER', 'curated'),
];
