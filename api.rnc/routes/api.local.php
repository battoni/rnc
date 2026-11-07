<?php

/*
 * Project-owned API routes — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * This file is required from routes/api.php, so register your feature-module routes here and
 * they survive every rnc update. Declare your own `use` imports (a required file does not
 * inherit the parent's).
 */

use App\Modules\Content\Controllers\PublicSectorPageController;
use App\Modules\Firm\Controllers\PublicFirmController;
use App\Modules\Tracking\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

// PUBLIC SITE (rnc-1 F2) — no session needed. The firm is the `X-Firm` header's, or `franco`
// (`ResolveCurrentFirm`); every answer goes through a `Public*` allow-list resource.
Route::get('public/firm', [PublicFirmController::class, 'show']);
Route::get('public/sectors', [PublicSectorPageController::class, 'index']);
Route::get('public/sectors/{slug}', [PublicSectorPageController::class, 'show']);

// Tracking (R7, AM2): 60 events a minute per address — a person browsing never meets it, a loop
// does. Keyed by IP for a visitor (Laravel's `throttle:<max>,<minutes>` signature).
Route::post('public/events', [PublicEventController::class, 'store'])->middleware('throttle:60,1');
