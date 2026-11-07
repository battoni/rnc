# Plan: Franco Reforma Hub — Phase 3 (growth)
ID: rnc-3 · Spec: `specs/rnc-3-reforma-hub-phase-3/spec.md` (closed 07/11/2026)
Approved: 07/11/2026 (Claude, owner away — `/battoni-run` mode, W# logged in `state.md`) ·
reviewed by a clean-context Sonnet agent 07/11/2026 → Amendments AM1–AM14 and "Plan review" below, then by `fable-reviewer` → AM15–AM23

Every phase: Pest tests red first for the API, then Vitest for the SPA, then code; then
`E2E_PORT=5176 make pre-commit` green (Node 24; stop any `npm run dev` of this worktree first — the
port is this worktree's own, `rnc` uses 5173 and `rnc-wt-2` 5175); then commit `rnc-3: F<n> <what>`.
API code follows `.claude/rules/arcus-api-architecture.md` (route → FormRequest → DTO → Service →
Repository → Resource → `ApiResponse`; modules under `api.rnc/app/Modules/<Name>/` with RULES.md +
CHANGELOG.md bumped); routes in `routes/api.local.php`; keys declared in
`app/Modules/Auth/Helpers/permission-groups.local.php` and granted in
`database/seeders/RoleSeeder.php` (`SeededRolesTest` updated). SPA code follows
`.claude/rules/celer-0*.md` (modules under `app.rnc/src/modules/<Name>/`, barrels `@XModule`, two
routes per screen pt-BR + en, rail in `navItems.local.ts` by permission, `MAddEdit{Entity}Form` in
`MMainDialog`, strings in `src/locales/{en,pt-BR}.json`). Behaviour reference: the demo files named
in the spec header.

## Carried up front (rnc-1 AM1–AM15, rnc-2 AM1–AM13 and both pitfall lists, applied before any code)
- **P0 Phase 2 underneath.** This branch starts at rnc-2's F1. At the start of every phase, if
  `feature/rnc-2-reforma-hub-phase-2` has commits this branch lacks, `git merge --no-edit` it
  (never rebase, never write in `rnc-wt-2`); resolve a fold conflict in a shared `create_*` migration
  by keeping both column sets, then `make fresh-seed` and the gate. **F6–F9 need rnc-2 finished**
  (its `state.md` shows F10 done and verified): portfolio leads (`findOrStartPortfolioLead`, D13),
  client consent (`clients.partner_consent`), the portal, `ReferralSla`, `client_events`,
  `RadarService`. If rnc-2 is not finished when F6 is due, the run stops with "blocked on rnc-2" —
  it does not stub Phase 2.
- **P1 Hosts and env** — `localhost` only; `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`. This
  worktree's live checks serve the API on 8013 (`php artisan serve --host=localhost --port=8013`)
  and the SPA on 5176 against its own `api.rnc/database/database.sqlite`.
- **P2 Migrations** — fold new columns into the owner's `create_*` (D15); portable SQL only (no
  `enum`, no raw SQL, Carbon bounds + `whereBetween`); cross-module ids carry no foreign key (rnc-1
  W25); dump the schema before/after a fold and diff it.
- **P3 Firm scope** — every new business table `BelongsToFirm`; seeders inside `CurrentFirm::runAs`,
  stamp `firm_id` themselves, refuse production, are called from `seeders.local.php`; commands
  iterate firms (`CurrentFirm::forEachFirm`).
- **P4 Time** — UTC columns use `UtcDateTime`; business-day maths in `America/Sao_Paulo` with
  Booking's `Holidays`; tests pin `CarbonImmutable::setTestNow` (São Paulo time).
- **P5 Money** — integer cents; a percentage is basis points; every product of a rate rounds half-up
  `intdiv(x × bp + 5000, 10000)` with an odd-cents test; ranges are `[lo, hi]`.
- **P6 Safe parsing** — CSV through the clients CSV's reader (UTF-8, `;`/`,`, size cap, line cap,
  no formula evaluation); text inputs length-capped in the FormRequest; the matcher works on a
  normalised copy, never a regex built from user text.
- **P7 HTTP** — `Http::preventStrayRequests()` once, in `tests/Pest.php`, from F1 (AM18); F1's builder fixes any inherited test that reaches real HTTP.
- **P8 Errors** — a refusal the SPA branches on has a stable `code`; another firm's row is 404, a
  missing key 403; the SPA branches on `code`, never on the sentence.
- **P9 SPA** — the rnc-1/rnc-2 SPA pitfalls (components registered in `global.components`, `Select`
  stubbed, `en` keys, no `.at()`, `motion-*`, wide strips `w-0 min-w-full overflow-x-auto`, no
  `class` beside `:class`, `text-primary-950` on tints).
- **P10 Route matrix** — every new route is classified in `tests/Feature/Access/routeClasses.php`
  (`staff` + its key, or `public`) in the phase that adds it; the matrix's parceiro-403 and
  guest-401 sweeps then cover it. Each phase adds its isolation test (firm B's row → 404) and its
  "equipe 403" cases to A25's file `tests/Feature/Access/Phase3AccessTest.php`.
- **P11 Brand guard** — every phase that adds an SPA module adds it to `noPartnerNetworkNames.spec.ts`.
- **P12 Proof** — an A# split across two phases is written red in the first and completed in the
  phase named in its header; A26 is proved by Verify.
- **P13 Aggregates** — every list or KPI answers with a fixed number of queries (grouped by
  `client_id`/`band`/`stage`), never one per row; F6 and F8 log the measured time on the seed in
  `state.md` (< 2 s).

## Phases

**F1 Brands API** (R1–R4 API side, R28 keys · proves A1, A3, A5; A4's Pest half; A25 rows)
- Auth `permission-groups.local.php` (changed: the seven keys of R28, declared now so later phases
  only grant); `RoleSeeder` (changed: nothing granted yet — operador holds every declared key).
- Firm module: new `create_firm_brands_table` (firm_id, slug 40 unique per firm, name 120,
  short_name 40, tagline 160, brand_primary, brand_accent, proof json — exactly 4 `{value ≤ 20,
  label ≤ 60}`, active, timestamps), `Models/FirmBrand.php` (`BelongsToFirm`),
  `Services/CurrentBrand.php` (scoped binding, null = main), `app/Http/Middleware/ResolveCurrentBrand.php`
  (reads `X-Brand` under `CurrentFirm`, 404 `brands.notFound` for unknown/inactive — except on `POST public/events`, where an unknown or inactive slug falls back to the main brand, so a stale tab never loses its analytics (AM21); alias `brand`,
  put on the public routes only), `PublicFirmResource` (changed: brand overrides name, logo text,
  colours, tagline, proof; address/phone/mailbox/hours stay the firm's), `Helpers/Contrast.php`
  (WCAG relative luminance, pure), `BrandService`, `BrandRepository`, `Requests/UpdateBrandRequest.php`
  (`#rrggbb`, 4 proof items, `brand.lowContrast` under 4,5:1 white-on-primary),
  `Resources/BrandResource.php`, `Controllers/BrandController.php`: `GET brands` (with lead counts in
  one grouped query), `PATCH brands/{id}`, `POST brands/{id}/activate|deactivate` — all
  `brands.manage`.
- Lead module (changed): `leads` create migration + `brand_id` (nullable, indexed, no FK);
  `ConsentTerms` takes the brand's name; the diagnostic start stores `CurrentBrand::id()` once and no
  later PATCH touches it; `GET leads?brand=<slug|main>` filter (unknown slug 422) in the Pipeline module; lead resources
  expose `brand {id, short_name}`.
- Booking (changed): the confirmation Mailable's from-name and signature use the lead's brand name
  (address: the firm's mailbox).
- Seed: `BrandSeeder` (new, local only — the demo's Express/Prime tokens, taglines, proof);
  `PipelineSeeder` (changed: ~10 % Express, ~10 % Prime leads).
- AM22: red step for the `Pest.php` line (A27, asserted in F9): delete the line, watch the fail, restore; the same builder does it when F9 lands.
- AM18: `tests/Pest.php` gains the global `beforeEach` calling `Http::preventStrayRequests()` in this phase; run the whole Pest suite, and fix every inherited test that hits real HTTP (fake it) before the commit. F9 keeps only A27's assertion that the line exists.
- AM21: `PublicBrandTest.php` also covers `POST public/events` with an unknown and an inactive slug → 200 on the main brand.
- Tests first: `tests/Unit/Modules/Firm/ContrastTest.php`, `tests/Feature/Firm/PublicBrandTest.php`
  (A1, incl. firm B's slug 404), `tests/Feature/Firm/BrandAdminTest.php` (A5),
  `tests/Feature/Diagnostic/BrandLeadTest.php` (A3, `Mail::fake`), `tests/Feature/Pipeline/BrandFilterTest.php`
  (A4 Pest half); `Phase3AccessTest.php` (new, brands rows); route classes updated.
- Done when: gate green; `curl -H 'X-Brand: express' localhost:8013/api/public/firm` answers Express
  with Franco's phone; `X-Brand: nada` → 404.

**F2 Brands on the site and the console** (R2–R5 UI · proves A2, A6; completes A4)
- AM3: `ThePublicLayout.spec.ts` and the consent component's spec assert the brand name in the
  consent sentence and that no other firm's name (the firm list of the seed, plus "Alvorada") renders;
  AM12: "Ver como" is disabled "Inativa" for an inactive brand.
- Site module: `usePublicFirm.ts` (changed: `?marca=` → `sessionStorage` `app.rnc:brand`, sends
  `X-Brand` on public calls through one helper, `franco` clears, any 404 with code `brands.notFound` clears the stored brand and reloads the main (AM21));
  `ThePublicLayout` (changed: CSS custom properties from primary/accent on the layout root, title,
  footer, preview banner when `previa=1`); hero/proof components and the consent sentence read the
  brand; Phase 2's chat bubble name ("Recepção <short>") if present after P0.
- New SPA module `Brands`: `BrandsView` at `/operador/marcas` (+ `/operator/brands`, `brands.manage`):
  rows (Marca principal, sub-brands, lead counts), `MAddEditBrandForm` in `MMainDialog` (colour
  inputs with a live contrast note, four proof items), activate/deactivate, "Ver como <short>"
  (`window.open` of the preview URL); services `getBrands`, `updateBrand`, `toggleBrand`; rail child
  "Marcas" under Operador.
- Pipeline (changed): `MLeadCard` + `LeadView` brand chip; board filter "Marca".
- Tests first: `usePublicFirm.spec.ts` (keeps, clears, any 404 `brands.notFound` clears the stored brand), `ThePublicLayout.spec.ts` (A2: CSS
  properties, title, banner only with `previa`), `BrandsView.spec.ts` + `MAddEditBrandForm.spec.ts`
  (A6, contrast refusal shown by `code`), `MLeadCard.spec.ts` (changed, A4), `navItems.local.spec.ts`
  (changed); E2E `e2e/site/brands.spec.ts` (A2 reload and clear, A6 banner).
- Done when: gate green; live at 390 and 1440, `/?marca=prime` shows Prime's colours and tagline with
  Franco's phone, no sideways scroll; a diagnostic walked there shows the Prime chip on the board.

**F3 RNC channel** — built as two phases (AM1): **F3a** the API (R6–R8 · proves A7, A8; A25 rows) and
**F3b** the screen (R8, R9 · proves A9). The lines below are tagged by where they go.
- F3a — new API module `Channel`: `config/channel.php` (`monthly_fee_cents` 350000, `share_bp` 1500),
  `create_channel_firms_table` (firm_id, name 120, city 80, stage string, stage_at UTC,
  monthly_fee_cents, share_bp, notes ≤ 500), `create_channel_firm_events_table` (firm_id,
  channel_firm_id, user_id, from, to, at UTC), `Enums/ChannelStage.php` (`next()`, `isTerminal`,
  `canTransitionTo`), `Helpers/ChannelShare.php` (pure, half-up), `Services/ChannelService.php`
  (summary: active Σ, per year, pending Σ, counts by stage — one grouped query; `advance(id, User)`
  in a transaction writing the event), repository, requests, resource, `ChannelController`:
  `GET channel` (`channel.read`), `POST channel/firms`, `PATCH channel/firms/{id}`,
  `POST channel/firms/{id}/advance` (`channel.manage`). Grants: ceo `channel.read`.
- F3a — AM15: `ChannelResource` omits `monthly_fee_cents` and `share_bp` unless the user holds `channel.manage`; without it a firm row carries its own `share_cents` (R$ 525/mês), stage and `stage_at`, and the summary only the share Σ, the per-year total and the counts (never the list price). Reason: `commercial.md:41`, the platform path is undecided and must be settled before Reinaldo sees it.
- F3a — seed: `ChannelSeeder` (the demo's 6 firms "(exemplo)", 3 ativo, `stage_at` 5–90 days back).
- F3b — SPA new module `Channel`: `ChannelView` (hero, stage counts, rows with the stepper and the share,
  "valor a conferir — sem cobrança automática"; controls only with `channel.manage`; AM15: without it the screen shows only each firm's share, the stage and the year total — no "R$ 3.500", no "pela plataforma" lede),
  `MAddEditChannelFirmForm`; routes `/operador/canal-rnc` (+ `/operator/rnc`) and
  `/reinaldo/canal-rnc` (+ `/ceo/rnc`), both `channel.read`; rail children under Operador and Reinaldo.
- F3a tests first: `tests/Unit/Modules/Channel/ChannelShareTest.php` + `ChannelStageTest.php` (A7
  unit, A8 graph), `tests/Feature/Channel/ChannelTest.php` (A7 sums, A8 moves/events/403/404, fee and
  share bounds 422 — AM9, seeded names all end "(exemplo)" — AM10, red step AM22: rename a seeded firm, watch it fail, restore; ceo's `GET channel` carries no `monthly_fee_cents`/`share_bp` key, operador's does — AM15); `Phase3AccessTest.php` rows;
  route classes. Done when: gate green; `curl POST channel/firms/{litoral}/advance` as battoni moves
  the hero R$ 1.575 → R$ 2.100/mês.
- F3b tests first: `ChannelView.spec.ts` (A9 with and without `channel.manage` — without it "R$ 3.500" and "pela plataforma" are absent — and none of
  "Willy", "APW", "Battoni", "divisão" rendered); E2E `e2e/channel/channel.spec.ts`. Done when: gate
  green; live, the operator advances a firm and Reinaldo's screen shows it without buttons.

**F4 Assistant API** (R10–R13, R15, R16 · proves A10, A11, A12, A15; A13's hand-off half, A14's Pest half; A25 rows)
- New API module `Assistant`: `config/assistant.php` (`driver` = `curated`), `create_assistant_notes_table`
  (firm_id, slug, question 200, answer ≤ 2000, rule 200, source_label 160, source_url 300, as_of date,
  keywords_strong json, keywords_weak json, status string, reviewer_id, reviewed_at UTC,
  last_edited_by), `create_assistant_questions_table` (firm_id, asked_by, specialist_id, question,
  status aberta|respondida, answer, answered_at), `create_assistant_asks_table` (firm_id, user_id,
  question, normalised, note_id nullable, at);
  `Contracts/ReformaAnswerer.php` + `Drivers/CuratedNotesAnswerer.php`, bound in
  `AppServiceProvider` from a `ASSISTANT_DRIVERS = ['curated' => …]` map that throws on an unknown
  driver (the `LoginCodeSender` pattern); `Helpers/QuestionNormalizer.php` + `Helpers/NoteMatcher.php`
  (pure, R12 scoring, ties); `Models/AssistantNote.php` (`VALID_DAYS = 90`, `scopeServed`);
  `Policies/AssistantNotePolicy.php` (review: `is_specialist` and not `last_edited_by` → else 403
  `assistant.notReviewer`; write: `assistant.manage` or a specialist); `Services/NoteService.php`
  (create, update → rascunho on a content change, review, retire), `Services/AskService.php` (log the
  ask, answer through the contract, `byId` for the chips), `Services/QuestionService.php` (hand-off to
  an active specialist of the firm — 422 `assistant.notSpecialist` otherwise; bell through
  `TeamNotifier`'s per-user send with a new `AssistantQuestionNotification`);
  `AssistantController`: `POST assistant/ask`, `GET assistant/suggestions` (served notes' questions),
  `POST assistant/questions` (`assistant.ask`); `NoteController`: `GET/POST assistant/notes`,
  `PATCH assistant/notes/{id}`, `POST assistant/notes/{id}/review` — `assistant.ask` + the policy;
  `POST assistant/notes/{id}/retire` and `GET assistant/gaps` (30 days, grouped by `normalised`,
  asks without a note only) — `assistant.manage` (AM4). Grants: equipe and ceo
  `assistant.ask`, ceo `assistant.manage`.
- Seed: `AssistantNoteSeeder` — the demo's 10 notes (`fiscal.js` 408–509) with `source_label`/`url`
  and `as_of` taken from `facts.md` §2 (each note's line cited in the seeder), reviewers the seeded
  specialists (sp1 Mariana, sp2 Carlos, sp3 Juliana, sp4 Rafael), `reviewed_at` relative to now (now − 1..5 days, so the seed is always inside the 90 days — AM19),
  `credito-simples` as rascunho (§2 "Unverified"); `last_edited_by` = reinaldo; two open
  `assistant_questions` (patricia → Mariana, patricia → Carlos) — AM2.
- Tests first: `tests/Unit/Modules/Assistant/NoteMatcherTest.php` (A11),
  `tests/Feature/Assistant/AskTest.php` (A12, firm B notes), `NoteReviewTest.php` (A10 with
  `setTestNow` at day 90 and 91), `AssistantGapsTest.php` (A14's Pest half), `QuestionHandoffTest.php` (A13 hand-off + bell), `AssistantDriverTest.php` (A15: unknown
  driver throws; a scan of `app/Modules/Assistant` finds no `Http::`, `Guzzle`, `curl_`, `fsockopen` — AM22 red step: put an `Http::get` in a dummy driver, watch it fail, remove it);
  `Phase3AccessTest.php` rows; route classes.
- Done when: gate green; `curl POST assistant/ask` as patricia with "quando começa a cobrança da
  CBS" answers `cbs` with "revisado por <seeded specialist> em <the seeded date>"; "qual o melhor ERP" answers
  `note: null`.

**F5 Assistant screens** (R12–R15 UI, R14 API · completes A13, A14)
- API (Assistant, changed): the notes list returns `expires_in_days` and a `expiring_count` (7 days); a daily `assistant:notify-expiring` (per firm, `forEachFirm`, scheduled 07:00 São Paulo) rings the note's reviewer at day 83 once (AM19); `GET assistant/questions` (own as specialist; all with
  `assistant.manage`), `POST assistant/questions/{id}/answer` (assigned specialist only, else 404;
  asker's bell), "Virar nota" = `POST assistant/notes` with `from_question`.
- New SPA module `Assistant`: `AssistantView` at `/equipe/assistente` (+ `/team/assistant`: greeting,
  input, suggestion chips, transcript in component state, `MAnswerCard` — answer, "Regra", "Fonte …,
  lida em …", "revisado por … em …" — `MHandoffCard` with the specialist picker, the counter line);
  `QuestionsView` at `/equipe/assistente/perguntas` (+ `/team/assistant/questions`: list, answer form,
  "Virar nota"); `KnowledgeView` at `/equipe/base-da-reforma` (+ `/team/knowledge`, `assistant.ask`; shown in the rail
  for `assistant.manage` or `is_specialist`; write/review/retire controls by the policy and key — AM4: notes with
  status chips incl. "revisão vencida", `MAddEditNoteForm`, review/retire, "Perguntas sem nota", and the counter "N notas vencem em 7 dias" (revisada notes whose `reviewed_at` is 83–90 days old — AM19));
  rail: "Assistente da Reforma" under Equipe (`assistant.ask`), "Base da Reforma" under Equipe (AM4).
- Tests first: Pest `tests/Feature/Assistant/QuestionAnswerTest.php` (A13 rest: answer bell, other
  specialist 404, virar nota); `tests/Feature/Assistant/NoteExpiryTest.php` (A28: day 82 no bell, day 83 one bell to the reviewer and not twice, counter 0 → 1); `AssistantView.spec.ts`, `MAnswerCard.spec.ts`, `QuestionsView.spec.ts`,
  `KnowledgeView.spec.ts` (A14 incl. gaps grouping shown); E2E `e2e/assistant/assistant.spec.ts`
  (ask → answer card; unknown → hand-off).
- Done when: gate green; live, patricia asks an unknown question and hands it to Mariana, Mariana's
  bell rings and her answer rings Patrícia's.

**F6 Contracts API** (R17–R22 API side · proves A16, A17, A18, A19; A20's Pest half; A25 rows) — **needs rnc-2 finished (P0)**
- *Split while building (07/11, owner away — state.md W75):* rnc-2 had merged only through its F7 when F6 was due, so F6 is built in two: **F6a** (built) — contracts table, exposure, CSV import, alert list and KPIs, "Cláusula incluída", seed, A16, A17, A20's Pest half, A25 contract rows; **F6b** (after rnc-2's F9 consent and F10 `findOrStartPortfolioLead` merge) — `GET contracts/legal-partners`, `POST contracts/{id}/referrals`, `referrals.contract_id`/`subject_key`, the alert rows' consent and "Com <parceiro>", the "Encaminhados ao jurídico" KPI, `PortalReferralResource`'s contract summary with the value range (AM20), the seed's ≥ 5 at-risk contracts without consent, A18, A19. F7 waits for F6b.
- Portfolio (changed): new `create_client_contracts_table` (firm_id, client_id, kind string,
  annual_value_cents, start_year, end_year, has_reforma_clause, clause_marked_at, clause_marked_by,
  unique (firm_id, client_id, kind, start_year, end_year)), `Enums/ContractKind.php`,
  `Helpers/ContractExposure.php` (pure: years `min(end, 2033) − max(start, 2027) + 1`, floor 0, e.g. 2030–2033 = 4 years — AM17; `LAST_YEAR`),
  `Services/ContractCsvParser.php` (reuses the clients CSV reader; R18 line errors with codes
  `client_unknown`, `year_invalid`, `end_before_start`, `kind_invalid`, `clause_invalid`),
  `ContractImportService` (AM16: on update it never downgrades `has_reforma_clause` once `clause_marked_at` is set — an empty clause column means keep, not "não"; on a row never marked, empty = não), `ContractService` (alert list + KPIs in fixed queries — P13; consent and
  "Com <parceiro>" joined, not per row; `markClause` → `ClientService::recordEvent('contrato.clausula')`),
  `ContractController`: `GET contracts`, `POST contracts/import`, `GET contracts/template`,
  `POST contracts/{id}/clause` (`contracts.manage`, import throttled `throttle:10,1,contracts-import`).
- Partner (changed): `referrals` create migration + `contract_id` (nullable, no FK) + `subject_key`
  string default `lead`, unique becomes (lead_id, partner_id, subject_key) — the Radar batch's
  "already referred" check keeps reading `subject_key = lead`; `ReferralService::referContract(contractId,
  partnerId, User)` (partner specialty `advocacia_tributaria`/`precificacao_controladoria` else 422
  `partner_not_legal`; client consent else 422 `consent_required`; `findOrStartPortfolioLead`; the
  demo message; 409 `already_referred`; `ReferralSent` with Franco's contact);
  `POST contracts/{id}/referrals` (`contracts.manage`); `GET contracts/legal-partners`;
  `PortalReferralResource` (changed, AM20: `contract {kind, end_year, sem_clausula, value_range}` for a contract
  referral — a range label such as "R$ 500 mil a 1 mi" by fixed bands, never the exact annual value; nothing else of the client). Step: write this confidentiality rule in the Partner module's `RULES.md` and bump its CHANGELOG. Grants: ceo `contracts.manage`.
- Seed: `ContractSeeder` (the demo's 40: kinds, R$ 40.000–1.200.000 a year, start 2022–2026, end
  2026–2033, 30 % with the clause; at least 5 at-risk ones on clients without consent).
- Tests first: `tests/Unit/Modules/Portfolio/ContractExposureTest.php`,
  `tests/Feature/Portfolio/ContractImportTest.php` (A16, incl. AM16: a marked contract re-imported with an empty clause keeps it), `ContractAlertTest.php` (A17),
  `tests/Feature/Partner/ContractReferralTest.php` (A18 incl. the Radar-vs-contract non-collision),
  `tests/Feature/Partner/PortalContractReferralTest.php` (A19: the payload holds no exact `annual_value_cents`), `ContractClauseTest.php` (A20's Pest
  half — AM5); `Phase3AccessTest.php` rows; route
  classes.
- Done when: gate green; `GET contracts` on the seed lists 20–30 at-risk rows in < 2 s (logged);
  `curl` sends one to Andrade and `parceiro.1`'s `GET portal/referrals` shows its contract summary with a value range, not the exact value.

**F7 Contracts screen** (R19–R22 UI, R21 UI · completes A20)
- New SPA module `Contracts`: `ContractsView` at `/reinaldo/contratos` (+ `/ceo/contracts`: note with
  the dated statement, four KPIs, rows with the '27–'33 chips and the exposure bar (min width 2 %),
  partner picker, disabled button + reason, "Com <parceiro>", "Cláusula incluída", import dialog
  `MContractImportDialog` with line errors and the template link); rail child "Alerta de contratos"
  under Reinaldo (`contracts.manage`).
- Portal (changed): `MPortalReferralCard` shows the contract summary for a contract referral.
- Tests first: `ContractsView.spec.ts` (A20: disabled with reason, sent chip, clause removes row),
  `MContractImportDialog.spec.ts`, `MPortalReferralCard.spec.ts` (changed); E2E
  `e2e/contracts/contracts.spec.ts`.
- Done when: gate green; live at 390 and 1440, Reinaldo sends a contract to Andrade and `parceiro.1`
  sees it with the summary.

**F8 Briefing API and e-mail** (R23–R25, R27 · proves A21, A22, A24; A25 rows) — **needs rnc-2 finished (P0)**
- New API module `Briefing` (owns no table; reads Lead, Booking, Partner, Portfolio, Radar):
  `Services/FeeQuartiles.php` (one grouped query of `clients.monthly_fee` by band, p25/p75 in PHP;
  `MIN_BAND_CLIENTS = 5`), `Services/BriefingService.php` (`build(now)`: the four KPIs, the panels,
  carteira leads excluded, a fixed number of queries — P13), `Helpers/ActionPicker.php` (pure, R25's
  order and copy, at most 3), `Resources/BriefingResource.php`, `GET briefing` (`briefing.read`);
  `Mail/DailyBriefingMail.php` (pt_BR locale, Franco's brand), `Console/SendDailyBriefing.php`
  (`briefing:send`: skips weekends and `Holidays`; per firm, every active ceo) scheduled
  `dailyAt('07:00')->timezone('America/Sao_Paulo')`. Grants: ceo `briefing.read`.
- Tests first: `tests/Unit/Modules/Briefing/ActionPickerTest.php` (A22), `FeeQuartilesTest.php`,
  `tests/Feature/Briefing/BriefingTest.php` (A21), `DailyBriefingCommandTest.php` (A24 with
  `Mail::fake` + `assertQueued` and `setTestNow` on a Friday, a Saturday, 20/11; the schedule's event
  for `briefing:send` has expression `0 7 * * *` and timezone `America/Sao_Paulo` — AM6); `Phase3AccessTest.php` rows; route
  classes.
- Done when: gate green; `GET briefing` on the seed answers non-zero KPIs in < 2 s (logged);
  `php artisan briefing:send` writes one mail to `laravel.log` for reinaldo.

**F9 Briefing screen and the closing sweeps** (R26 · proves A23, A27; completes A25)
- API: `tests/Feature/Access/NoOutwardClientsTest.php` (A27: `composer.json` and `app/` carry no
  LLM, embedding or video SDK — `openai`, `anthropic`, `gemini`, `vimeo`, `youtube`, `mux` —;
  `tests/Pest.php` calls `Http::preventStrayRequests()` in a global `beforeEach` since F1 (AM18; so a file reaching
  HTTP without naming `Http::` is covered too — AM8) and this test only asserts the line exists, `config('mail.default')` is
  `log` under `.env.example`); `Phase3AccessTest.php` reviewed so every new route of F1–F8 has its
  parceiro, equipe and firm B line (A25 complete).
- New SPA module `Briefing`: `BriefingView` at `/reinaldo/briefing` (+ `/ceo/briefing`; greeting by
  São Paulo hour, KPI tiles, actions with `RouterLink` buttons, `MCountdown` ticking every second
  with the dated caption, the three panels); Home `Workspace/routes.ts` (changed: `/reinaldo` →
  briefing instead of métricas); rail child "Briefing do dia" first under Reinaldo (`briefing.read`).
- Tests first: `BriefingView.spec.ts` (A23 with fake timers), `MCountdown.spec.ts`, the Workspace
  routes spec (changed: `/reinaldo` lands on the briefing); E2E `e2e/briefing/briefing.spec.ts`.
- Done when: gate green; live, Reinaldo signs in, `/reinaldo` opens the briefing, every action button
  lands on its screen.

**Verify** — `/battoni-verify rnc-3`: full suites, the reviewer on the diff
(`feature/rnc-2-reforma-hub-phase-2..`), fixes, then the Chrome rounds (A26): the Express and Prime
sites and a lead from each, the brands console and "Ver como", the RNC advance (operator) and
Reinaldo's read-only view, ask → hand-off → answer → virar nota → review by another specialist,
contract → partner's portal, the briefing and its links, the access sweep (parceiro and equipe
bounce), at 390 and 1440 against this worktree's API (8013) and SPA (5176) on `make fresh-seed`.

## Amendments from the plan review (07/11/2026) — each OVERRIDES the step it amends
Each AM is also written into its phase; where they disagree, the AM wins.
- **AM1 F3 split.** F3 (API + SPA, ~30 files) becomes F3a (API, A7, A8) and F3b (screen, A9). Nothing
  else is renumbered. The plan has 10 builds (F1, F2, F3a, F3b, F4–F9).
- **AM2 Seeded questions (F4; R29).** `AssistantNoteSeeder` also seeds two open questions.
- **AM3 Brand in consent (F2; A2).** The specs named in F2 assert the brand in the consent sentence and
  the absence of any other firm's name.
- **AM4 Who reaches the knowledge base (F4, F5; R10, R11, A25).** Notes list/create/edit/review are
  `assistant.ask` + `AssistantNotePolicy` (write: `assistant.manage` or `is_specialist`; review:
  `is_specialist` and not the last editor); retire and gaps are `assistant.manage`. The screen is
  `/equipe/base-da-reforma`, so a specialist can review; the ceo writes and never reviews (he is not
  a specialist). A25's "equipe 403 on `assistant.manage` routes" means retire and gaps.
- **AM5 Clause API proof (F6; R22, A20).** `ContractClauseTest.php` (Pest) proves the flag, the
  timeline line and the row leaving the list; F7 completes A20 in the SPA.
- **AM6 Schedule and queue (F8; R27, A24).** `DailyBriefingMail` implements `ShouldQueue` (sync
  locally); the test asserts the schedule event's cron and timezone, not only the command.
- **AM7 Exact rules (F4, F6, F8).** Ties in the matcher: newest `reviewed_at`, then the lower id; a
  note is served at exactly 90 days and not at 91; ask and hand-off questions 3–300 characters,
  answers ≤ 2000; contract years exposed `min(end, 2033) − max(start, 2027) + 1`, floor 0 (A17 adds a
  2028–2030 contract = 3 years); CSV lines are independent, empty clause = não; KPIs per R19; fee
  quartiles nearest-rank on cents parsed from `clients.monthly_fee`'s decimal string (never through a
  float), empty portfolio → no range; the referral tile counts contract and Radar referrals too (R23);
  after 01/01/2027 00:00 the countdown reads "CBS em vigor desde 01/01/2027".
- **AM8 Global stray-request guard (F9; A27).** `tests/Pest.php` calls `Http::preventStrayRequests()`
  for every test; the scan test asserts that line exists instead of scanning per file.
- **AM9 Channel bounds (F3a; R7).** Fee 1–10.000.000 cents, share 0–5000 bp, else 422.
- **AM10 Seed hygiene (F3a, F4, F6).** Each new seeder's test (or the phase's feature test after
  running it in-memory) asserts invented markers: channel firms end "(exemplo)", seeded contracts sit
  on seeded clients only, notes carry no person outside the seeded staff.
- **AM11 Plain referrals keep working (F6; D11).** `subject_key` defaults to `lead`; rnc-1's
  `ReferralService::refer` duplicate check and rnc-2's Radar "already referred" check filter
  `subject_key = 'lead'` explicitly; `ContractReferralTest` re-runs one plain refer and one batch.
- **AM12 Ver como inactive (F2; R5).** The button is disabled "Inativa" for an inactive brand.
- **AM13 Brand filter by slug (F1; A4).** `brand=<slug|main>`, unknown 422.
- **AM14 A25 per phase.** Every API phase's header names its A25 rows; F9 checks the whole file.

## Amendments from the Fable plan review (07/11/2026) — each OVERRIDES the step it amends
`fable-reviewer` (owner had granted `#fable`) judged no AM1–AM14 wrong. Each AM is also written into its phase, R# and A#; where they disagree, the AM wins.
- **AM15 Channel price is not Reinaldo's (F3a, F3b; R9, A8, A9; high).** `ChannelResource` omits `monthly_fee_cents` and `share_bp` without `channel.manage`. Reinaldo's read-only screen shows only his share (R$ 525/mês per firm), the stage and the year total — no platform list price, no "pela plataforma" lede. A9 adds: without `channel.manage`, "R$ 3.500" and "pela plataforma" are not rendered; A8 covers the resource. Reason: `commercial.md:41`, the platform path is undecided and must be settled before showing it to Reinaldo.
- **AM16 Import never downgrades a marked clause (F6; R18, A16; high).** On update, `ContractImportService` keeps `has_reforma_clause` when `clause_marked_at` is set (empty clause column = keep). A16 / `ContractImportTest` gets a case that fails on the old rule (empty = não).
- **AM17 Exposure formula (F6; medium).** `Helpers/ContractExposure.php` uses `min(end, 2033) − max(start, 2027) + 1`, as AM7/R19/A17; 2030–2033 = 4 years.
- **AM18 Stray-request guard from F1 (F1, F9; P7, A27; medium).** `Http::preventStrayRequests()` in `tests/Pest.php` moves to F1; F1's builder fixes any inherited test that hits real HTTP; F9 keeps only A27's assertion that the line exists.
- **AM19 Notes expire visibly (F4, F5; R11, new A28; medium).** The F4 seed sets `reviewed_at` relative (now − 1..5 days). F5 adds the "N notas vencem em 7 dias" counter on the knowledge base and a bell to the reviewer at day 83. A28 (Pest): day 82 no bell, day 83 one bell (not repeated), the counter counts notes 83–90 days old.
- **AM20 Partner sees a range (F6; R21, A19; medium).** `PortalReferralResource` sends contract type, end year, "sem cláusula" and a value range, never the exact annual value (client confidentiality); recorded in the Partner module's `RULES.md` in F6.
- **AM21 Stale brand (F1, F2; R2; low).** `ResolveCurrentBrand`: on `POST public/events` an unknown or inactive slug falls back to the main brand; the SPA helper clears the stored brand on any 404 `brands.notFound`.
- **AM22 Red steps for the guards (F1, F3a, F4, F6; low).** Per shared-conventions each guard is seen red once, written in the phase: A15 — add an `Http::get` in a dummy driver under `app/Modules/Assistant`, watch `AssistantDriverTest` fail, remove it; A27 — delete the `Pest.php` line, watch `NoOutwardClientsTest` fail, restore; AM10 — rename a seeded channel firm / note person / contract client, watch the seed-hygiene assertion fail, restore. The builder logs each in `state.md`.
- **AM23 D1 is cited (spec D1; low).** D1 cites `commercial.md:13` ("tenants") and says why sub-brands of one firm still stands: `operator.js:5,131` — "mesmos dados, mesma rede, mesmo endereço".

## Defaults
Sub-brand contrast 4,5:1 · RNC fee R$ 3.500/month, share 15 % · note validity 90 days, matcher
minimum score 2, gaps window 30 days · contract exposure window 2027–2033, CSV ≤ 2 MB / 5.000 lines,
import throttle 10/min · briefing: leads 7 days (24 h sub), meetings 5 business days (today sub),
SLA 24/12 business hours (rnc-2), fee quartiles p25–p75, band fallback under 5 clients, e-mail 07:00
São Paulo on business days.

## Plan review (Sonnet, clean context, 07/11/2026)
29 findings. Applied: AM1–AM14 above and the spec fixes they name (R7 bounds, R10 screen and
retire/gaps key, R11 day 90, R12 ties and length, R18 partial import and empty clause, R19 years and
KPIs, R23 referral tile, R24 quartile method, R26 after the date, A4, A9, A10, A14, A18, A20, A24).
Rejected: "every phase but F7/F9 is over ~10 files" — rnc-1's and rnc-2's phases were the same size
(one module's API or screen with its tests) and closed in one session; only F3, the one phase with
API and SPA together, is split. "Done-when curls are manual" — they are the live check on top of the
gate, as in rnc-1/rnc-2, not a proof; every A# has an automated test. A26 by Verify only is P12's
rule, as in rnc-2 (A23).
