# Changelog — Database (factories, seeders, migrations)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).


## 1.15.0 — 2026-11-07

### Changed

- rnc-2 F10 (R27, D13): `create_leads_table` gains `client_id` (nullable, no foreign key — Portfolio's id) and a unique `(firm_id, client_id)` (folded, D15; schema diffed before and after on SQLite: only the column and the index).

## 1.14.0 — 2026-11-07

### Changed

- rnc-2 F9 (R28): `create_clients_table` gains `partner_consent` (nullable bool), `partner_consent_at` (UTC) and `partner_consent_via` (folded, D15; schema diffed before and after: only the three columns). `ClientSeeder` gives ~85 % of the 500 clients consent `via contrato` (a second seeded generator, profiles and fees unchanged). `RoleSeeder` gives `ceo` `radar.manage`.

## 1.13.0 — 2026-11-07

### Added

- rnc-2 F7: `create_client_events_table` (Portfolio's client timeline — firm, client, user without a foreign key, type, data json, `at` UTC).

### Changed

- `RoleSeeder`: the team (`equipe`) — and so Reinaldo — gets `fiscal.read` (NF-e readiness and supplier credit, D14); `SeededRolesTest` states it.

## 1.12.0 — 2026-11-07

### Added

- rnc-2 F6b: `NfeSeeder` (local only — refuses production and `testing`, plan AM7): it clears `storage/app/private/nfe/<firm id>/`, then imports 90 days of invented NF-e for the first 40 Presumido/Real clients (15–60 sales, 10–40 purchases each; the demo's readiness tiers and `SIMPLES_SHARE_BY_BAND`) through `NfeImporter`, one `folder` batch per client, and prints the counts and its time; called from `seeders.local.php` after `ClientSeeder`. `seeders/Support/NfeXmlBuilder` (the invented NF-e XML, shared with the Fiscal tests — F6a's hand-written fixtures moved onto it) and `GeneratedNfeSource`.

## 1.11.0 — 2026-11-07

### Added

- rnc-2 F6a: the Fiscal module's `nfe_imports` (one batch: who, how, files, stored, refused json, at UTC) and `nfe_documents` (one NF-e per client: unique `(firm_id, access_key, client_id)` — spec C1 — and an index on `(firm_id, client_id, direction, issued_at)`; `client_id` with no foreign key, W25) as new `create_*` migrations (`000015`, `000016`); `RoleSeeder` gives `ceo` `fiscal.import`. `.schema` dumped before and after `migrate` and diffed: the two new tables and their indexes, nothing else.

## 1.10.0 — 2026-11-07

### Changed

- rnc-2 F5: `PipelineSeeder` starts one lead in eight in the chat (`channel = chat`, consent `via = chat`, ~12 %, the demo's weight); `LeadFactory` names its channel by `LeadChannel`.

## 1.9.0 — 2026-11-07

### Added

- rnc-2 F4: `firms` gains `partner_money_model` (default `comissao`), `partner_commission_rate_bp` (1000) and `partner_monthly_fee_cents` (49000); `referrals` gains an index on `(firm_id, closed_at)` — both folded into their `create_*` migrations (D15). `.schema firms` and `.schema referrals` diffed before and after `migrate:fresh`: the three columns and the index, nothing else.

## 1.8.0 — 2026-11-07

### Added

- rnc-2 F2: `referrals` gains `status_at`, `status_by` (no foreign key — W25), `contacted_at`, `meeting_at`, `deal_value_cents`, `closed_at` and an index on `(partner_id, status)`, folded into its `create_*` migration (D15). `PartnerSeeder` (local only) seeds the demo's twelve referrals (`REFERRAL_PLAN`: 3 `enviado` at 3/15/30 business hours, 4 `fechado` priced by band) with one `encaminhamento.status` line per step. `.schema referrals` read after `migrate:fresh`: the six columns and the index, nothing else.

## 1.7.0 — 2026-11-07

### Added

- rnc-2 F1: `users.partner_id` (nullable, unique, no foreign key — W25) and `partners.access_granted_at` / `access_revoked_at` folded into their `create_*` migrations; the new `partner_access_events` table; `RoleSeeder` writes the fourth role `parceiro` (`portal.access`) and gives `ceo` `ledger.read`; `PartnerUserSeeder` (local only) seeds `parceiro.1`…`parceiro.8`. Schema dumped before and after `migrate:fresh` and diffed: only those changes.

## 1.6.0 — 2026-09-22

### Added

- `ManagerSeeder` merges `Auth/Helpers/user-exclusions.local.php` into the keys it withholds from
  the member role (`getLocalUserExclusions()`), the same LOCAL seam as the Manager list. From
  largatudoevai.

## 1.5.0 — 2026-09-22

### Changed

- The seeded `user` role no longer holds `tips.read`. It opened the Dicas management screen to every
  self-registered member — archived tips and each tip's history, with the name of the staff member
  who edited it and the reason they wrote down. The tips themselves render through `tips/active`,
  which asks for no key, so a member loses nothing they use (owner's decision, 2026-09-22; raised by
  the independent audit of PR #39). `SeedShapeTest` and `SeededRolesTest` assert it; both seen red
  with the exclusion removed.

## 1.4.0 — 2026-09-20

### Changed

- The seeded `user` role no longer holds `users.read` or `users.reset_password` (ARC-15). The first
  returned every account's name, e-mail and phone to anybody who signed themselves up, and the
  documentation portal read that same key as "this person is staff", so a member walked into the
  internal docs (LP-DOCS-01). A member has no account but their own to recover, which is the second.
  `SeedShapeTest` asserts both are absent.

## 1.3.1 — 2026-09-17

### Fixed

- `SuperAdminSeeder` writes the plaintext `config('seeding.superadmin_password')` and lets the `hashed` cast hash it, as this unit's rule already said; it pre-hashed with `Hash::make`. Covered by `tests/Feature/SeededPasswordTest.php` — the stored column is still a hash that verifies, and an arch test keeps `Hash` out of `Database\Seeders` (red before the change) (from chapaja-342).

### Documented

- The idempotency bullet names `firstOrCreate`, which every seeder uses, instead of `updateOrCreate`, which none does.
- Demo data runs on demand, never in `seeders.local.php` — the Seeders section contradicted its own demo-data bullet (from largatudoevai-068).

## 1.3.0 — 2026-09-16

### Added

- `ManagerSeeder` merges `Auth/Helpers/manager-exclusions.local.php` (LOCAL) into the keys it withholds from the Manager, so a project denies a key without editing the synced seeder. The core exclusion (`design.read`) stays in the seeder. Covered by `tests/Feature/Auth/ManagerLocalExclusionsTest.php` (from largatudoevai-016).

### Fixed

- `RULES.md` "Do Not" said to read secrets with `env()` and a default, contradicting the Seeders section, which requires `config('seeding.*')`. It now points at `config/seeding.php` and says why the old line was wrong (lifeshifter review).

## 1.2.0 — 2026-09-14

### Added

- `RULES.md`: demo seeders run on demand, idempotent by a natural key, recalculating what they derive (from largatudoevai and lifeshifter); a state with a moment is a timestamp (from lifeshifter); a log row's reference carries no foreign key (from largatudoevai).

### Changed

- `ManagerSeeder` withholds `design.read` from the manager and the member roles. The administrator receives it with every other key through `RoleSeeder`. Those pages were gated on the administrator's role before, so the move to a permission hands them to nobody new.

- RULES: a permission for a screen that is not open to people yet stays out of the seeded role lists (from lifeshifter).
- `tests/Feature/SeedShapeTest.php` states the seeded install exactly — three profiles, three accounts, the administrator holding every key — and `tests/Feature/Auth/SeededRolesTest.php` lists the member's and the manager's keys one by one, so a change to a seeded list is a decision made in the test and not a side effect of a recovery command (from lifeshifter).

### Fixed

- `ManagerSeeder` describes the manager as every feature except the design gallery; it said "all features" after `design.read` left the list.

## 1.1.0 — 2026-09-14

### Added
- `SEEDER_PWD` — one variable for every seeded password except the superadmin's (from chapaja).
- `config/seeding.php` — seeders and the factory read both passwords through config; `env()` returned null under a cached config.

### Changed
- `users.role_id` is declared in `create_users_table`; `roles` moved to `0000_00_00_000000` and the `add_role_id_foreign_to_users_table` ALTER is gone.
- `UserFactory` always makes ACTIVE users, with the plaintext `SEEDER_PWD`.
- `ManagerSeeder` withholds `permissions.read` / `permissions.update` from the member role.

### Removed
- The `test@example.com` account `DatabaseSeeder` created on every environment.

### Fixed
- `RULES.md` claimed the factory assigns `Role::factory()` and a bcrypt literal; neither was true.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
