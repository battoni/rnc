<?php

/*
 * Review of 2026-09-17 (ARC-17): `NewsShareController` read `NewsRepository` directly, skipping
 * the service, so the "a draft is not shareable" rule lived in a controller while `NewsService`
 * kept the same rule for reads and reactions. Route → Controller → Service → Repository
 * (arcus-api-architecture §1): a controller in these modules never reaches a repository.
 */

arch('Idea controllers go through a service')
    ->expect('App\Modules\Idea\Controllers')
    ->not->toUse('App\Modules\Idea\Repositories');

arch('Tip controllers go through a service')
    ->expect('App\Modules\Tip\Controllers')
    ->not->toUse('App\Modules\Tip\Repositories');
