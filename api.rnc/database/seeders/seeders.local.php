<?php

use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\SectorPageSeeder;

/*
 * Project-owned seeders — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * This file is required from DatabaseSeeder::run(), so it executes in that method's scope —
 * `$this->call(...)` is available. Register your feature-module seeders here and they survive
 * every rnc update. Example:
 *
 *   $this->call(\Database\Seeders\ReferenceSeeder::class);
 */

// The public site's six sector pages (rnc-1 R6) — reference content, every environment.
$this->call(SectorPageSeeder::class);

// The pilot's team (Reinaldo, the team, the four specialists) — refuses to run in production.
$this->call(LocalUsersSeeder::class);
