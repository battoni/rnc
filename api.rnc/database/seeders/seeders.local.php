<?php

use Database\Seeders\AssistantNoteSeeder;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\ClientSeeder;
use Database\Seeders\ContractSeeder;
use Database\Seeders\DemoFirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\MetricsSeeder;
use Database\Seeders\NfeSeeder;
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

// The demo firm Alvorada (rnc-4 F1a, D18): invented firm, manager and specialist — LOCAL ONLY
// (refuses testing and production, AM7), before the specialists' hours it gets.
$this->call(DemoFirmSeeder::class);

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

// The contract alert (rnc-3 F6): the demo's 40 contracts on those invented clients, about 30 % with
// the Reforma clause — local only (refuses production), after the portfolio it reads.
$this->call(ContractSeeder::class);

// Franco's RNC channel (rnc-3 F3a): the demo's six "(exemplo)" member firms, three active, on the
// default fee and share — local only (refuses production).
$this->call(ChannelSeeder::class);

// The Reforma knowledge base (rnc-3 F4): the demo's ten notes citing facts.md §2 (one a draft), reviewed
// 1–5 days back by the four specialists, and two open questions — local only (refuses production),
// after the people it names.
$this->call(AssistantNoteSeeder::class);
// The clients' NF-e (rnc-2 F6b): 90 days of invented XMLs for 40 Presumido/Real clients, imported
// through `NfeImporter` — local only (refuses production and the Pest suite, AM7), after the
// portfolio it reads. It first clears `storage/app/private/nfe/<firm id>/`.
$this->call(NfeSeeder::class);
