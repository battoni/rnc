<?php

use Database\Seeders\LocalUsersSeeder;

/*
 * Project-owned seeders — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * This file is required from DatabaseSeeder::run(), so it executes in that method's scope —
 * `$this->call(...)` is available. Register your feature-module seeders here and they survive
 * every rnc update. Example:
 *
 *   $this->call(\Database\Seeders\ReferenceSeeder::class);
 */

// The pilot's team (Reinaldo, the team, the four specialists) — refuses to run in production.
$this->call(LocalUsersSeeder::class);
