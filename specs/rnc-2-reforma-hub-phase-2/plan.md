# Plan: Franco Reforma Hub — Phase 2 (client data and the partner network)
ID: rnc-2 · Spec: `specs/rnc-2-reforma-hub-phase-2/spec.md` (closed 07/11/2026)
Approved: 07/11/2026 (Claude, owner away — `/battoni-run` mode, W# logged in `state.md`) ·
reviewed by `fable-reviewer` 07/11/2026 → Amendments AM1–AM13 below

Every phase: Pest tests red first for the API, then Vitest for the SPA, then code; then
`make pre-commit` green (stop `npm run dev` first); then commit `rnc-2: F<n> <what>`. API code
follows `.claude/rules/arcus-api-architecture.md` (route → FormRequest → DTO → Service → Repository →
Resource → `ApiResponse`; modules under `api.rnc/app/Modules/<Name>/` with RULES.md + CHANGELOG.md
bumped); routes go in `routes/api.local.php`; permission keys are declared in
`app/Modules/Auth/Helpers/permission-groups.local.php` and given in `database/seeders/RoleSeeder.php`
(`SeededRolesTest` updated). SPA code follows `.claude/rules/celer-0*.md` (modules under
`app.rnc/src/modules/<Name>/`, barrels `@XModule`, two routes per screen `-en`/`-pt-BR`, rail in
`navItems.local.ts` by permission, `MAddEdit{Entity}Form` in `MMainDialog`, strings in
`src/locales/{en,pt-BR}.json`). Behaviour reference: the demo files named in the spec header.

## Carried from rnc-1 up front (its AM1–AM15 and pitfalls, applied before any code)
- **P1 Hosts and env** — AM1 unchanged (`localhost` only, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`).
- **P2 Migrations** — not launched: fold new columns into the owner's `create_*` (D15), never an
  ALTER; portable SQL only (AM11: no `enum`, no raw SQL, Carbon bounds + `whereBetween`, `json` ok);
  dump the schema before/after a fold and diff it. Cross-module ids carry no foreign key (rnc-1 W25).
- **P3 Firm scope** — every new business table `BelongsToFirm`; seeders inside `CurrentFirm::runAs`
  and stamp `firm_id` themselves (`WithoutModelEvents`); commands iterate firms; queued mail carries
  a plain DTO. Every new or changed seeder refuses production (R29) and is called from
  `seeders.local.php`.
- **P4 Time** — UTC columns use the `UtcDateTime` cast; ISO-8601 with offset on the wire;
  business-hour maths in `America/Sao_Paulo` with Booking's `Holidays`; tests pin `Carbon::setTestNow`.
- **P5 Money** — integer cents (`*_cents`, `Money`, SPA `formatReal`); ranges are `[lo, hi]`.
- **P6 Throttles** — every public or upload route has a prefixed limiter (`throttle:N,M,<prefix>`).
- **P7 HTTP** — `Http::preventStrayRequests()` in every Pest file that touches HTTP; a dataset
  closure is never a `Closure` parameter (rnc-1 pitfall).
- **P8 Errors** — a domain failure the SPA branches on has a stable `code`; another firm's or
  partner's row is 404, a missing key 403.
- **P9 SPA** — Vitest registers components in `global.components`; PrimeVue `Select` stubbed in
  jsdom; `en` keys for every string; no `.at()`; `motion-*` utilities; a wide strip
  `w-0 min-w-full overflow-x-auto`; no `class` beside `:class`.
- **P11 Brand guard** — every phase that adds an SPA module adds it to `noPartnerNetworkNames.spec.ts`
  (no Willy/APW).
- **P12 Proof** — where an A# has a Pest half and a Vitest half in two phases, the earlier phase
  writes its half red-first and the later phase, named in its header, completes the proof. A23 is
  proved by Verify.
- **P10 Route matrix** — from F1 on, every new route is classified in
  `tests/Feature/Access/routeClasses.php` (public · portal · staff + key · shared) or A2's test
  fails; the matrix walks `routes/api.php` and `routes/api.local.php` (AM2).

## Phases

**F1 Partner access** (R1–R4 · proves A1, A2, A4)
- API files: `users` create migration + `partner_id` (changed, nullable, indexed; `app/Models/User.php`
  relation); Auth `permission-groups.local.php` (changed: `portal.access`, `fiscal.read`,
  `fiscal.import`, `radar.manage`, `ledger.read` declared now so later phases only grant);
  `RoleSeeder` (changed: role `parceiro` = `portal.access`; ceo gets `ledger.read` — its other new
  keys are granted in their phases; `operador` holds every declared key from F1 on, as it always
  does, and is refused by the portal for lacking a `partner_id`); `partners` create migration
  (changed: `access_granted_at`, `access_revoked_at`, UTC — the current state only) + new
  `create_partner_access_events_table` (firm, partner_id, user_id, type granted|revoked, at UTC —
  `PartnerAccessService` writes one row per grant and revoke; F4's subscription months read these,
  AM9); Partner module: `Services/PartnerAccessService.php`,
  `Controllers/PartnerAccessController.php` (`POST partners/{id}/access`, `POST …/access/password`,
  `DELETE …/access`, `partners.manage`; a second active login 409 `partner_access_exists`; the
  e-mail and username follow the bootstrap's `users` uniqueness rules; the user is created with
  `firm_id = partner.firm_id` — AM1), `Requests/CreatePartnerAccessRequest.php`,
  `Resources/PartnerAccessResource.php` (password only on create/regenerate); regenerate and revoke
  delete the user's `sessions` rows, and revoke also sets the user inactive (AM5); partner
  deactivation also deactivates its user (`PartnerService`, changed); `app/Modules/Partner/Services/CurrentPartner.php`
  (scoped binding) + `app/Http/Middleware/ResolveCurrentPartner.php` (loads the Partner of
  `users.partner_id` through `FirmScope` under `CurrentFirm`, 403 `portal.noPartner` when absent;
  registered in `bootstrap/app.php` as the route alias `portal`, put on every `portal/*` route —
  never global, AM1); `GET portal/me` (partner name, specialty, firm) behind
  `permission:portal.access` + `portal` → 403 `portal.noPartner` without a partner; `UserResource` gains
  `partner_id`.
- Tests first: `tests/Feature/Partner/PartnerAccessTest.php` (A1, incl. the old cookie → 401
  `auth.accountInactive` right after revoke — AM5), `tests/Feature/Access/RouteMatrixTest.php`
  + `routeClasses.php` classifying every existing route of `api.php` + `api.local.php` in four
  classes public · portal · staff + key · `shared` (any authenticated user 2xx, guest 401:
  `auth/me`, `PATCH auth/me`, `auth/logout` — AM2) (A2), `tests/Feature/Partner/PortalMeTest.php`.
- SPA files: new `src/modules/Portal/` (`ThePortalLayout` or a `layout: portal` meta, `routes.ts`
  `/parceiro` + `/partner` with `meta.permission: portal.access`, `PortalHomeView` placeholder with the
  partner's name, `getPortalMe.service.ts`); `Home` route `home` redirects a user holding
  `portal.access` and not `home.read` to the portal (changed); `navItems.local.ts` (changed: portal
  rail only for `portal.access`, staff groups never for it); Partner `PartnersView` +
  `MPartnerAccessForm` (create / regenerate / revoke, password shown once with copy).
- Tests first: `Portal/routes.spec.ts`, `navItems.local.spec.ts` (changed), `MPartnerAccessForm.spec.ts`;
  E2E `e2e/portal/access.spec.ts` (partner lands on `/parceiro`, `/painel` bounces) — A4.
- Seed: `LocalUsersSeeder` → `PartnerUserSeeder` (new, after `PartnerSeeder`): `parceiro.1`…`parceiro.8`,
  password `config('seeding.password')`; `state.md` → Local environment lists them.
- Done when: `make pre-commit` green; `parceiro.1` signs in through the SPA against the served API
  and lands on its portal; `curl` with its cookie on `GET leads` → 403.

**F2 Portal API — statuses, SLA, contact** (R5–R9 API side · proves A3, A5, A6, A7)
- Partner module: `ReferralStatus` (changed: `allowedTransitions`, `canTransitionTo`, `isTerminal`,
  `label`); `referrals` create migration (changed: `status_at`, `contacted_at`, `meeting_at`,
  `deal_value_cents`, `closed_at`, `status_by`); `Helpers/BusinessHours.php` (Franco's week from the
  firm's hours, Booking `Holidays`) + `Services/ReferralSla.php` (states of R8, 24/12 h constants);
  `Services/PortalReferralService.php` (list for `CurrentPartner`, show, `transition(id, DTO)` in a
  transaction: guard graph, write row, `LeadService::recordReferralStatus` → `encaminhamento.status`
  line, `TeamNotifier` on aceito/recusado/fechado via new `ReferralStatusNotification` in the
  Notification module); `Repositories/PortalReferralRepository.php` (always
  `where partner_id = CurrentPartner::id()`); `Requests/TransitionReferralRequest.php`
  (`to`, `meeting_at` future, `deal_value_cents` > 0 by `to`); `Resources/PortalReferralResource.php`
  (allow-list; contact only from `aceito` on; portfolio referral → Franco's contact; never ranges);
  `Controllers/PortalController.php`: `GET portal/referrals`, `GET portal/referrals/{id}`,
  `POST portal/referrals/{id}/transition` — all `permission:portal.access`. A portfolio referral's
  way in is the firm's public contact (`firms`, rnc-1 R1).
- Lead module (changed): `LeadEventType::EncaminhamentoStatus`, `LeadService::recordReferralStatus`.
- Seed: `PartnerSeeder` (changed): the demo's `REFERRAL_PLAN` of 12 (3 enviado at −3/−15/−30
  business hours, 4 fechado with `dealHint` values by band), statuses with their dates.
- Tests first: `tests/Unit/Modules/Partner/ReferralStatusTest.php` + `BusinessHoursTest.php` +
  `ReferralSlaTest.php` (A6 graph, A7); `tests/Feature/Partner/PortalReferralsTest.php` (A3 isolation:
  two partners, two firms, and partner A sending `X-Firm` of firm B → 403/404 — AM1; A5 allow-list before/after accept; A6 feature 422s; A8's Pest half: line +
  notifications + stage unchanged); route classes updated.
- Done when: pre-commit green; `curl` as `parceiro.3` walks one seeded referral enviado → fechado and
  `laravel.log`/DB show the timeline lines and notifications.

**F3 Portal SPA and Franco's view** (R5–R10 UI · proves A8)
- Portal module: `PortalInboxView` (KPIs, "Próximas reuniões", list), `MPortalReferralCard`
  (profile, covered items, message, SLA chip, contact once accepted, actions by status),
  `MReferralMeetingForm`, `MReferralCloseForm` (in `MMainDialog`), `MSlaChip` (shared, exported),
  services `getPortalReferrals`, `transitionReferral`.
- Partner module (changed): `MLeadRouting` shows status + `MSlaChip` + contract value per referral;
  `TrailView` adds status and SLA columns.
- Notification bell renders the new `kind`s (Parceiro aceitou / recusou / fechou contrato).
- Tests first: `PortalInboxView.spec.ts`, `MPortalReferralCard.spec.ts` (actions per status, contact
  hidden before accept, refused moves show the API message), `MLeadRouting.spec.ts` and
  `TrailView.spec.ts` (changed: status + SLA); E2E `e2e/portal/inbox.spec.ts` (accept → contato →
  reunião → proposta → fechado).
- Done when: pre-commit green; live, `parceiro.1` closes a referral and Patrícia's bell rings within
  30 s, the lead's routing panel shows "Fechado · R$ …".

**F4 Ledger** (R11 · proves A9)
- API: `firms` create migration (changed: `partner_money_model` string default `comissao`,
  `partner_commission_rate_bp` 1000, `partner_monthly_fee_cents` 49000) + Firm `FirmSettingsService`
  (the only writer); Partner `Services/LedgerService.php` — `statementFor(partner, month)` and
  `networkStatement(month)` (+ the other model's total). A closing belongs to the month of its
  `closed_at` in `America/Sao_Paulo`; a partner owes the subscription for a month when its access
  existed on any day of it, derived from the access event rows (`partner_access_events`: granted /
  revoked, UTC, one row per change — `access_granted_at`/`access_revoked_at` stay as the current
  state only, AM9); commission per closing = `intdiv(deal_value_cents * rate_bp + 5000, 10000)`
  (half-up, AM9). Endpoints `GET portal/statement?month=` (`portal.access`), `GET ledger?month=`
  (`ledger.read`), `PATCH ledger/model` (`partners.manage`).
- SPA: Portal `PortalStatementView` (`/parceiro/extrato` + `/partner/statement`, month ◀ ▶, lines,
  "Total devido à Franco", never the other model); Partner `LedgerView` at `/reinaldo/extrato`
  (+ `/ceo/ledger`) with the model switch (`partners.manage`), every partner's month, the total and
  the other model's; both carry "valor a conferir — sem cobrança automática"; rail child "Extrato da
  rede" under Reinaldo (`ledger.read`); services `getPortalStatement`, `getLedger`, `updateLedgerModel`.
- Tests first: `tests/Feature/Partner/LedgerTest.php` (3.600 / 490, month boundaries, partner sees
  only its lines, equipe 403 on ledger and model; revoke → regrant in a later month still bills the
  first month; R$ 12.345,67 at 10 % → R$ 1.234,57 — AM9), `tests/Unit/Modules/Partner/LedgerServiceTest.php`;
  `PortalStatementView.spec.ts`, `LedgerView.spec.ts` (switch reloads both totals, label shown).
- Done when: pre-commit green; live, closing a referral moves the partner's extrato and Reinaldo's
  total; switching to assinatura shows R$ 490 × partners with access.

**F5 Chat receptionist** (R12–R14 · proves A10, A11, A12)
- API (changed): Lead `LeadChannel` enum (`formulario`, `chat`, `carteira`) replacing
  `FORM_CHANNEL`; `StartDiagnosticRequest` accepts `channel ∈ {formulario, chat}` (default
  formulario); `ConsentTerms::VIA_CHAT`; the contact PATCH takes `via` from the lead's channel;
  `PipelineService`/lead resources expose `channel`; Metrics `FunnelService::byChannel`
  (formulário · chat, from `diag.start`) in `FunnelResource`. Seed: `PipelineSeeder` (changed) makes
  ~12 % of its leads `chat` (demo weight).
- SPA Diagnostic module: `constants.ts` turn table; `composables/useChatReceptionist.ts` over a
  `ReceptionistScript` (pure: turn → prompt, options, `match(text)`), driving `useDiagnostic` (no
  second copy of the answers); `MChatReceptionist` (transcript, quick replies, input, "Recepção
  Franco" bubbles, `role=alert` errors), `MChatResult` (items with chips, lock, the same
  `MSlotPicker`); `DiagnosticView` toggle "Formulário · Conversar no chat" (changed). Pipeline
  `MLeadCard` + `LeadView` channel chip; Metrics `MFunnelTable` "Por canal".
- Tests first: `ReceptionistScript.spec.ts` (every turn's matcher, "Não entendi…", contact validation —
  A11), `useChatReceptionist.spec.ts` (A10's proof: for "Simples, B2B 80 %, serviços" the sequence of
  PATCH bodies the chat produces deep-equals the form's — AM8) + `MChatReceptionist.spec.ts` (channel
  chat on start, switch mid-way keeps answers), E2E `e2e/diagnostic/chat.spec.ts` (the chat walk
  reaches the result and the slot picker; no item-count claim — the API is mocked, so result-count
  parity is checked in Verify's Chrome round, AM8);
  `MLeadCard.spec.ts` + `MFunnelTable.spec.ts` (changed: chip, "Por canal");
  Pest `tests/Feature/Diagnostic/ChatChannelTest.php` (channel + consent via; a later PATCH or a
  form start on the same visitor never changes the channel) and
  `tests/Feature/Metrics/FunnelByChannelTest.php` (A12).
- Done when: pre-commit green; live, a chat walk to booking creates a `chat` lead that shows the chip
  on the board and +1 in "Por canal".

**F6 NF-e import** — built as two phases (AM12): **F6a** parser, key, importer and upload (proves
A13, A14's XML half, A15) and **F6b** ZIP, command, builder, seeder and batch list (proves A14's ZIP
and command half, A24). The list below is tagged per item; nothing else is renumbered.

**F6a NF-e parser, key, importer, upload** (R15–R17 · proves A13, A15; A14's XML half)
**F6b NF-e ZIP, command, seeder, batches** (R15, R17 · proves A14, A24)
- F6a: new Fiscal `RULES.md` records that `POST fiscal/readiness/{id}/notice` and
  `POST fiscal/supplier-credit/{id}/review` write under `fiscal.read` on purpose — they only add a
  client timeline line, no fiscal data — so reviewers do not reopen it (AM13).
- F6a XML safety (AM3): any content containing `<!DOCTYPE` (string check, before `loadXML`) is
  refused `not_nfe`; never `LIBXML_PARSEHUGE`; the stored filename is built only from the validated
  access key and the integer client id.
- F6b ZIP and folder safety (AM3): never trust `statIndex` sizes — read each entry through
  `getStream` capped at 1 MB + 1 byte (over → `too_large`), total read cap 20 MB; skip directories,
  nested `.zip` and non-`.xml` names; `nfe:import` resolves the folder with `realpath`, accepts a
  directory only and skips symlinks.
- F6a: `NfeImporter`, `NfeXmlParser`, `AccessKey`, `UploadedNfeSource` for a single XML,
  `POST fiscal/imports`, migrations, `NfeImportTest` A13/A15 + A14's XML fixtures. F6b: ZIP in
  `UploadedNfeSource`, `FolderNfeSource`, `ImportNfeFolder`, `GET fiscal/imports`, `NfeXmlBuilder`
  (F6a's tests may start with hand-written fixtures, F6b moves them onto the builder), `NfeSeeder`,
  `NoOutwardClientsTest` (A24).
- New API module `Fiscal`: migrations `create_nfe_imports_table` (firm, user, source upload|folder,
  files, stored, refused json, at) and `create_nfe_documents_table` (firm, client_id, import_id,
  direction saida|entrada, access_key 44 — unique per (firm, key, client): a document between two
  portfolio clients is stored twice, once as each one's saída/entrada, number, series, issued_at UTC, emitter_cnpj,
  emitter_crt, recipient_cnpj nullable, recipient_kind cnpj|consumidor, total_cents, system
  (`verProc`), items, items_missing_group, items_missing_codes, items_without_test_values,
  sample json, path); `Contracts/NfeDocumentSource.php` (`#[Bind]` none — two drivers chosen by the
  caller: `Sources/UploadedNfeSource.php` (XML or ZIP read in memory, no extraction to disk, ≤ 20 MB,
  ≤ 2000 entries, ≤ 1 MB per XML) and `Sources/FolderNfeSource.php`); `Services/NfeXmlParser.php`
  (`DOMDocument` with `LIBXML_NONET`, no `LIBXML_NOENT`, DTD refused; tag paths per `facts.md` §5,
  read defensively); `Helpers/AccessKey.php` (mod-11, uses `app/Helpers/Brazil/Cnpj`);
  `Services/NfeImporter.php` (match client by CNPJ through Portfolio's repository — read only, store
  raw at `storage/app/private/nfe/<firm>/<client>/<key>.xml`, refusals with codes);
  `Controllers/NfeImportController.php` `POST fiscal/imports` (`fiscal.import`, throttle 10/min prefix
  `nfe-import`), `GET fiscal/imports` (batches, paginated); `Console/ImportNfeFolder.php`
  (`nfe:import <folder> --firm=`); ceo gets `fiscal.import`.
- `database/seeders/Support/NfeXmlBuilder.php` (new — builds valid invented `nfeProc` XML strings;
  used by tests and the seeder) and `NfeSeeder` (new, local only, deterministic seed 2027): 40
  Presumido/Real clients, 90 days, 15–60 sales and 10–40 purchases each, readiness tiers ≈ the demo's
  46/50/4 %, supplier CRT by the demo's `SIMPLES_SHARE_BY_BAND`, recipients CNPJ vs CPF by the
  client's B2B share (CPFs root `999`, valid digits), run through `NfeImporter` (one code path).
  `NfeSeeder` first deletes `storage/app/private/nfe/<firm>/`; the Pest suite never runs it (tests
  build their own documents); its run time is recorded in `state.md` → Local environment (AM7).
- Tests first: `tests/Unit/Modules/Fiscal/AccessKeyTest.php`, `NfeXmlParserTest.php` (fixtures from
  the builder; XXE fixture — A14: an external entity pointing at `file:///etc/hostname` whose
  content appears nowhere in the stored row, the file or the refusal, and a billion-laughs fixture
  refused — AM3), `tests/Feature/Fiscal/NfeImportTest.php` (A13 incl. the re-upload of a document
  shared by two clients — AM4, A14 ZIP + command incl. oversize entry, nested zip and symlink,
  A15 no URL + firm isolation), `tests/Feature/NoOutwardClientsTest.php` (A24: an `arch()` that no
  class under `app/` uses a SEFAZ/LLM/bank SDK namespace, and every test file using `Http::fake`
  also calls `preventStrayRequests`).
- Done when (F6a): pre-commit green; `curl` uploading one XML as `ceo` stores it and a second upload
  reports `duplicate`. Done when (F6b): pre-commit green; `make fresh-seed` imports the seeded XMLs
  (counts and seed time printed), and `php artisan nfe:import` on a folder of three builder files
  reports 1 stored, 1 duplicate, 1 `client_unknown`.

**F7 Fiscal analytics API** (R18–R23 API side · proves A16, A18)
- Fiscal module: `Services/ReadinessService.php` (30-day window, Presumido/Real, status by
  `ReadinessStatus` enum with 95/80 plus `sem_notas` for a client without sales, listed last, issues + up to 5 sample numbers, system filter),
  `Services/SupplierCreditCalculator.php` (pure; R21 range; constants with the `facts.md` §5
  citation) + `SupplierCreditService.php`, `Services/SplitPaymentCalculator.php` (pure; R23 from XML
  or cadastro, `basis` das_notas|cadastro; the cadastro basis uses `RulesEngine`'s band revenue —
  the demo's representative 240 mil · 1,8 mi · 12 mi · 90 mi · 450 mi; no endpoint of its own, the
  Radar reads it); endpoints `GET fiscal/readiness?status=&system=`,
  `GET fiscal/readiness/{clientId}`, `GET fiscal/supplier-credit?sector=` (KPI "revisões propostas" =
  clients with a `fornecedores.revisao` event) (`fiscal.read`); `POST fiscal/readiness/{clientId}/notice` and
  `POST fiscal/supplier-credit/{clientId}/review` (`fiscal.read` on purpose, recorded in Fiscal
  `RULES.md` — AM13; review 422 `no_simples_purchases`). `ReadinessService` reads the window with
  one aggregate query grouped by `client_id` per call and does the maths in PHP — no query per
  client (AM6).
- Portfolio module (changed): `create_client_events_table` (firm, client_id, user_id, type, data, at),
  `ClientService::recordEvent` (the only writer), `GET clients/{id}/events` (`fiscal.read`).
- Grants: equipe and ceo get `fiscal.read`.
- Tests first: `tests/Unit/Modules/Fiscal/SupplierCreditCalculatorTest.php` (A18),
  `SplitPaymentCalculatorTest.php` (A19's unit half), `tests/Feature/Fiscal/ReadinessTest.php` (A16),
  `SupplierCreditTest.php`, `ClientEventsTest.php` (A17's Pest half); route classes updated.
- Done when: pre-commit green; `curl` on the seeded DB returns readiness rows in all three statuses
  and a supplier-credit list whose top row's range matches the formula by hand; `GET fiscal/readiness`
  on the seed answers in < 2 s (measured, logged in `state.md`).

**F8 Fiscal screens** (R18–R22 UI, R17 UI · proves A17)
- New SPA module `Fiscal`: `ReadinessView` at `/equipe/nfe` (+ `/team/invoices`: hero, KPIs, status
  bar, status/system filters, table, `MReadinessDetail` with issues and samples,
  `MClientNoticeDialog` with the e-mail text, "Copiar texto", "Registrar aviso"),
  `SupplierCreditView` at `/equipe/fornecedores` (+ `/team/suppliers`: KPIs, sector filter, rows
  with the two-part bar and the range, "Propor revisão" disabled at 0 %, "Revisão proposta" chip),
  `NfeImportView` at `/reinaldo/notas` (+ `/ceo/invoices`: upload XML/ZIP, the batch result with
  refusals by file, batches list) for `fiscal.import`; `MClientTimeline` (shared); rail children
  "Prontidão da NF-e" and "Crédito de fornecedores" under Equipe (`fiscal.read`), "Notas fiscais"
  under Reinaldo (`fiscal.import`).
- Tests first: `ReadinessView.spec.ts`, `MClientNoticeDialog.spec.ts` (register → line),
  `SupplierCreditView.spec.ts` (disabled at 0 %, chip after propose), `NfeImportView.spec.ts`
  (refusals listed), `navItems.local.spec.ts` (changed); E2E `e2e/fiscal/readiness.spec.ts`.
- Done when: pre-commit green; live at 390 and 1440 the three screens load on the seed with no
  console error and no sideways scroll; an upload of a builder ZIP shows its batch.

**F9 Radar API and client consent** (R24–R26, R28 · proves A19, A20, A22)
- Portfolio (changed): `clients` create migration + `partner_consent` (nullable bool),
  `partner_consent_at`, `partner_consent_via`; `ClientCsvParser`/`ClientImportService` accept the
  optional `autoriza_parceiros` (sim/não/empty, case- and accent-insensitive: `sim`, `SIM`, `não`,
  `nao`, `NÃO`, `Não` all accepted — AM11; else a line error `consent_invalid`); template gains
  the column; `ClientSeeder` (changed) ~85 % consent `via contrato`.
- New API module `Radar` (no table; reads Portfolio, Fiscal, uses Diagnostic `RulesEngine` and
  Fiscal's calculators; a client's profile is `Client::profile()` — ICMS incentives and export false,
  rnc-1 C11 — with the measured B2B share when D9 applies): `Services/EvidenceBuilder.php` (measured B2B share by value when ≥ 10 sales
  documents — D9; per-rule proof; up to 3 keys; one aggregate query per call grouped by `client_id`,
  maths in pure PHP — AM6), `Services/RadarService.php` (`summary()` — totals,
  types sorted, opportunity Σ only; `type(rule)` — clients with ranges and evidence; split row Σ of
  R23), `GET radar`, `GET radar/types/{rule}` (`radar.manage`); ceo gets `radar.manage`.
- Tests first: `tests/Feature/Radar/RadarSummaryTest.php` (A20; A19's Radar Σ),
  `tests/Unit/Modules/Radar/EvidenceBuilderTest.php`, `tests/Feature/Portfolio/ClientConsentImportTest.php`
  (A22, with `nao`, `NÃO`, `Não`, `SIM` — AM11); route classes updated.
- Done when: pre-commit green; `curl GET radar` on the seed analyses 500 clients in < 2 s (measured,
  logged in `state.md` — AM6) and the split row equals Σ of `SplitPaymentCalculator` over the
  clients (`GET fiscal/split` was dropped).

**F10 Radar screen and batch referral** (R27 · proves A21)
- API: Lead (changed) `leads` create migration + `client_id` nullable (indexed, no FK);
  `LeadService::findOrStartPortfolioLead(client)` (`channel carteira`, stage `cliente`, no contact,
  copies the client's name → `company`, `cnpj`, and sector, regime, band and B2B share → `profile`
  — AM10;
  consent row copied from the client `via contrato` with `ConsentTerms::CONTRACT_TEXT`); Partner
  `ReferralService::referBatch(rule, partnerId, User)` (covering partners only → 422
  `partner_not_covering`; top 10 by the range's top, ties by client id, not yet referred to that
  partner, with consent; each referral's score and covered items come from `PartnerMatcher` on the
  client's profile, the message is the demo's Radar text ("A Franco indica a <nome>, cliente da
  carteira desde <ano>: o Radar da Reforma apontou “<título>”…"); one
  transaction; one `ReferralSent` each, with Franco's contact); `POST radar/referrals` (`radar.manage`)
  → `{sent, skipped_no_consent, skipped_already}`; `GET radar/types/{rule}` adds per-partner
  "already sent" counts; `PortalReferralResource` handles the carteira lead (Franco's contact).
- SPA new module `Radar`: `RadarView` at `/reinaldo/radar` (+ `/ceo/radar`: KPIs, opportunity and
  risk panels, rows with rule, count, range, partner picker of covering partners, "Encaminhar os N
  maiores" / "Todos já encaminhados", "N sem autorização ficam fora do lote", split row line),
  `MRadarTypeDetail` (clients with evidence and keys, "do cadastro"); rail child "Radar da carteira"
  under Reinaldo.
- Tests first: `tests/Feature/Radar/RadarBatchReferralTest.php` (A21: ≤ 10, consent and already-sent
  skipped, covering only, carteira lead + consent via contrato, visible in the partner's portal
  inbox with `company` set in `PortalReferralResource` — AM10, second batch 0); `RadarView.spec.ts`, `MRadarTypeDetail.spec.ts`; E2E `e2e/radar/radar.spec.ts`.
- Done when: pre-commit green; live, Reinaldo sends a batch and `parceiro.4`'s inbox shows the
  portfolio referrals with Franco's contact.

**Verify** — `/battoni-verify rnc-2`: full suites, the reviewer on the diff
(`feature/rnc-1-reforma-hub-pilot..`), fixes, then the Chrome rounds (A23): partner walk, Franco's
view of it, chat walk, fiscal screens, Radar batch, the access sweep (partner bounces, staff never
see the portal) at 390 and 1440 against `make serve-api` + `make serve-app` on `make fresh-seed`.

## Amendments from the plan review (fable-reviewer, 07/11/2026) — each OVERRIDES the step it amends
Every AM below is also written into the phase it amends, so a builder reading only its phase sees
it; where a phase line and an AM disagree, the AM wins.
- **AM1 Partner firm (F1, F2; A3).** Overrides F1's `PartnerAccessService` and `ResolveCurrentPartner`.
  A partner user with no `users.firm_id` fell through `ResolveCurrentFirm` (lines 35-48) to the
  `X-Firm` header, so partner A could send `X-Firm` of firm B and read firm B's referrals of a
  partner with the same id. `PartnerAccessService` creates the user with
  `firm_id = partner.firm_id`; `ResolveCurrentPartner` loads the Partner through `FirmScope` under
  `CurrentFirm` and answers 403 `portal.noPartner` when it is not found; it is the route alias
  `portal` on every `portal/*` route, never global middleware. A3 gains "partner A with `X-Firm` of
  firm B → 403/404" (F2's `PortalReferralsTest`).
- **AM2 Route matrix `shared` class (F1; A2).** Overrides P10 and F1's matrix. `auth/me`,
  `PATCH auth/me` and `auth/logout` (`routes/api.php` 14-18) fit none of public · portal · staff. A
  fourth class `shared`: any authenticated user 2xx, guest 401. The matrix walks `routes/api.php` and
  `routes/api.local.php`.
- **AM3 XML and ZIP safety (F6a, F6b; A14).** Overrides F6's parser and source lines. Any content
  containing `<!DOCTYPE` is refused `not_nfe` by a string check before `loadXML`; never
  `LIBXML_PARSEHUGE`. ZIP: never trust `statIndex` sizes — read each entry through `getStream`
  capped at 1 MB + 1 byte (over → `too_large`), total cap 20 MB, skip directories, nested `.zip` and
  non-`.xml` names. The stored filename comes only from the validated key and the integer client id.
  `nfe:import` resolves the folder with `realpath`, accepts a directory only, follows no symlink. A14
  gains an external-entity fixture pointing at `file:///etc/hostname` (its content appears nowhere)
  and a billion-laughs fixture refused.
- **AM4 R16 uniqueness (F6a; A13).** The spec said unique per firm + key; the plan stores a document
  between two portfolio clients twice. Recorded in spec.md → Changes while building (C1): unique per
  firm + key + client. A13 defines the re-upload of a document shared by two clients: the second
  upload stores nothing and reports one `duplicate` per client row already stored (two lines for the
  file, both `duplicate`); a first upload stores two rows and no refusal.
- **AM5 Revoke kills sessions (F1; A1).** Overrides F1's access service. Revoke and regenerate delete
  the user's `sessions` rows (`SESSION_DRIVER=database`); revoke also sets the user inactive. A1
  asserts the old cookie gets 401 `auth.accountInactive` (`EnsureAccountActive`) right after revoke.
- **AM6 No N+1 (F7, F9).** Overrides `ReadinessService` and `EvidenceBuilder`. One aggregate query per
  call, grouped by `client_id`, then pure-PHP maths. F7 and F9 "Done when" gain a measured ceiling:
  `GET fiscal/readiness` and `GET radar` answer in < 2 s on the seed, the number logged in `state.md`.
- **AM7 NfeSeeder hygiene (F6b).** `NfeSeeder` first deletes `storage/app/private/nfe/<firm>/`; the Pest
  suite never runs it; its run time goes in `state.md` → Local environment.
- **AM8 A10's proof (F5).** Overrides F5's E2E claim. "Same number of items" cannot fail against a
  mocked API. The proof becomes a Vitest deep-equal of the PATCH sequence `useChatReceptionist`
  produces against the form's, for "Simples, B2B 80 %, serviços"; the result-count parity moves to
  Verify's Chrome round (A23's chat walk).
- **AM9 Ledger history and rounding (F1, F4; A9).** Overrides F4's subscription rule. One
  `access_granted_at`/`access_revoked_at` pair loses a revoke → regrant history. F1 adds
  `partner_access_events` (granted/revoked, UTC, one row per change, written by
  `PartnerAccessService`); F4 derives the billed months from those rows; the two columns stay as the
  current state. Commission per closing: `intdiv(deal_value_cents * rate_bp + 5000, 10000)`
  (half-up), with an odd-cents test: R$ 12.345,67 at 10 % → R$ 1.234,57.
- **AM10 Portfolio lead content (F10; A21).** `findOrStartPortfolioLead` copies the client's name
  (→ `company`), CNPJ, and sector, regime, band and B2B share (→ `profile`) onto the lead. A21
  asserts `company` in `PortalReferralResource`.
- **AM11 Consent values (F9; A22).** `autoriza_parceiros` is case- and accent-insensitive: `sim`, `SIM`,
  `não`, `nao`, `NÃO`, `Não` all accepted; A22 includes them.
- **AM12 F6 split (F6).** F6 becomes F6a (parser, key, importer, upload; A13–A15) and F6b (ZIP,
  command, builder, seeder, batch list; A24). Nothing else is renumbered.
- **AM13 Fiscal writes under `fiscal.read` (F6a, F7).** `POST …/notice` and `POST …/review` only add
  a client timeline line, so they stay under `fiscal.read`; F6a writes this into Fiscal `RULES.md`
  so reviewers do not reopen it.

## Defaults
SLA 24 business hours, warn 12 · commission 10 % · subscription R$ 490/month · readiness window 30
days, thresholds 95/80 · purchases and B2B window 90 days · reference rate 26,5 %, DAS share 3–5 %,
split settled share 40–60 % · evidence threshold 10 sales documents · Radar batch 10 · upload ≤ 20 MB,
≤ 2000 files, ≤ 1 MB per XML · import throttle 10/min · seeded consent 85 %.

## Plan review (Sonnet, clean context, 07/11/2026)
Applied: R29 production guard and the brand guard for every new module (P3, P11); the ledger split
out of F2 into its own F4 (F2 was ~25 files); `sem_notas`, the revisions KPI, the band revenue, the
portfolio profile, the batch's order, score and message, the two-row rule for a document between
two clients, the subscription months (`access_granted_at`/`access_revoked_at`), the 409 code and
the operator's keys written into their phases; the channel-immutability test and the missing specs
(`MLeadCard`, `MFunnelTable`, `TrailView`); `GET fiscal/split` dropped (the Radar reads the
calculator). Rejected: "every phase is over ~10 files" — rnc-1's F1–F8 were the same size (one
module's API or screen with its tests) and each closed in one session under budget; only F2 was
split. A# halves across two phases are allowed by P12, the proof named in the later phase's header.
