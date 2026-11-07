<?php

use Database\Seeders\BrandSeeder;
use Database\Seeders\ClientSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\MetricsSeeder;
use Database\Seeders\PartnerSeeder;
use Database\Seeders\PartnerUserSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\SectorPageSeeder;
use Database\Seeders\SpecialistSeeder;

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

// Every `is_specialist` member's weekly hours, from the firm's business hours (rnc-1 R15). After
// the people, so the four specialists exist; on production it finds none until one is flagged.
$this->call(SpecialistSeeder::class);

// Franco's sub-brands (rnc-3 F1): Express and Prime with the demo's colours, taglines and proof —
// local only (refuses production), before the pipeline that starts a few leads under each.
$this->call(BrandSeeder::class);

// The team's board and agenda (rnc-1 F5): 40 invented leads over 30 days, a few booked meetings —
// local only (refuses production), after the specialists it books.
$this->call(PipelineSeeder::class);

// The network (rnc-1 F6): the demo's eight "(exemplo)" partners and a few referrals of consenting
// leads in the trail — local only (refuses production), after the pipeline it refers from.
$this->call(PartnerSeeder::class);

// The partners' portal logins (rnc-2 F1): `parceiro.1`…`parceiro.8`, one per seeded partner, the
// seeding password — local only (refuses production), after the partners it signs in as.
$this->call(PartnerUserSeeder::class);

// Reinaldo's funnel (rnc-1 F7): 30 days of invented traffic and the beacons of every seeded lead's
// own walk — local only (refuses production), after the pipeline whose leads it walks.
$this->call(MetricsSeeder::class);

// Reinaldo's portfolio to reprice (rnc-1 F8): 500 invented clients with plainly invented CNPJs —
// local only (refuses production).
$this->call(ClientSeeder::class);
