# State: Reforma hub as a white-label platform for accounting firms
Stage: build F5b/19
Updated: 14/11/2026
Branch: feature/rnc-4-white-label-platform · Worktree: .claude/worktrees/rnc-4
## Done
Intent approved 13/11 (delegated) · spec closed 13/11 (40 R · 49 A · 31 D, owner decisions O1–O12) ·
plan approved 13/11 (delegated): F1a F1b F2a F2b F2c F3a F3b F3c F4a F4b F6a F6b F5a F5b F4c F4d F7
(+ optional F9a F9b), then verify. Baseline suites: 1851 Pest · 1289 Vitest · 180 Playwright.
- **F1a Firm by host — API** (A1–A5; AM1, AM2, AM4 API half, AM8, AM9) · 13/11 · full Pest 1917
  (+66: `FirmByHostTest`, `FirmMismatchTest`, `PlatformSeedersTest`, `LoginFirmTest`,
  `UsersFirmScopeTest`, `RegistrationClosedTest`, `StatefulHostTest`), all green; Vitest and
  Playwright not run (no app.rnc change; F1b runs both in full, AM5) · Pint, PHPStan, mirror check
  green · pre-commit green 13/11/2026 (arcus review inline: constants extracted, docs bumped).
  Red first: 56 of the 66 new tests failed before the code (A1 and the own-host cases already held).
  Mutations seen red: platform firm by header (AM9), firm-less non-operator at root, mismatch check
  removed (D5). Not caught: `UserService::delete`'s own firm guard (the controller's scoped `find`
  404s first — defence in depth).
  P6 security list (for verify): firm chosen in `ResolveCurrentFirm` (session firm / platform firm
  / header via `FirmResolver::findTenantBySlug`) and `QaLoginAsController::requestedFirm` (header,
  else Host); login refused in `AuthService::authenticate`, `LoginCodeService::findAccount`,
  `QaLoginAsController` (all `FirmResolver::belongsTo`); user reads scoped in `UserRepository`.
  Existing tests changed for D5 (header now 403, not ignored): `FirmScopeTest`, `PortalMeTest`,
  `PortalReferralsTest`, `LedgerTest`; `SeedShapeTest` (hub follows franco); registration tests run
  under `registration.enabled`; Auth unit tests get `CurrentFirm`/`FirmResolver`.
- **F1b Firm by host — SPA and local hosts** (A6, A7; AM3, AM4 SPA half, AM5) · 13/11 · full Vitest
  1311 (+22: `firmHost.helper.spec`, `FirmNotFoundView.view.spec`, and new cases in `provider.spec`,
  `errorHandler.helper.spec`, `TheRouteGuard.spec`, `usePublicFirm.spec`, `useTracking.spec`) · full
  Playwright 183 (+3: `e2e/tenancy/firm-host.spec.ts`) · full Pest 1917 (no API change) · all green ·
  vue-tsc, aliases, ESLint, Prettier, Pint, PHPStan, mirror check green · pre-commit green 13/11/2026
  (vue + design reviews inline, nothing to fix).
  Red first: all 11 new Vitest cases and the 2 new spec files failed before the code. Mutations seen
  red (all 3 e2e): `X-Firm` dropped from the provider, the mismatch branch removed from
  `errorHandler`, the not-found swap disabled in `TheRouteGuard`.
  What changed: `firmHost.helper` (`firmSlugFromHost`, `currentFirmSlug`, `rootSiteUrl`); the provider
  sets `X-Firm` after a service's own headers; `API_URL` defaults to `/api`, `ROOT_DOMAIN` new;
  `errorHandler` treats 403 `firms.mismatch` like a 401; `usePublicFirm().isFirmMissing`;
  `TheRouteGuard` asks the firm once on a firm host and renders `FirmNotFoundView` (pt-BR "Escritório
  não encontrado", link to the root) in place of any path; Vite `server`/test/`preview` proxy `/api`
  (keeps Host, `VITE_API_PROXY_TARGET` overrides the target) and `allowedHosts: ['.localhost']`;
  Playwright `baseURL` `http://franco.localhost:<port>` (D31); `.env.example`, `.env.test` (retired
  comment, AM4) and this worktree's `app.rnc/.env` carry `VITE_API_URL=/api`, `VITE_ROOT_DOMAIN`.
  Existing e2e touched: `fixtures/auth.ts` answers `public/firm` by default (W13). Every e2e intercept
  already matched `**/…` on any host.
  Live (AM3, through Vite's proxy, fresh seed): `public/firm` on `alvorada.localhost:5173` names
  Alvorada; `patricia` 422 on alvorada, 200 on franco (`auth/me` 200 on the session); patricia's
  pipeline 40 leads, `helena.alvorada`'s 0; franco session + `X-Firm: alvorada` 403 `firms.mismatch`;
  `xyz.localhost` 404 `firms.notFound`; root → `hub`; the session cookie is host-only (no Domain).
- **F2a Firm brand, product name, mail brand, consent versions — API** (A8–A10; R8, R9, R12, R13) ·
  14/11 · full Pest 1932 (+15: `Firm/PublicFirmBrandTest` 6, `Mail/FirmMailBrandTest` 3,
  `Lead/ConsentVersionTest` 6), all green; Vitest and Playwright not run (no app.rnc change) · Pint,
  PHPStan, mirror check green · pre-commit green 14/11/2026 (arcus review inline: the seeded consent
  default made write-once, unused update methods dropped, `based-on` bumped). `make fresh-seed` clean.
  Red first: all 15 new tests failed before the code. Mutations seen red: `platform` dropped from
  `PublicFirmResource`, the firm logo shown under a sub-brand, `auth/me` without `withPlatform`, the
  sender back on the firm's mailbox, the band not the firm's primary, links at the root, a new
  consent text rewriting v1, the sub-brand's name not filled, v1 not copied from the platform default.
  What changed: `firms` + `short_name`, `tagline`, `proof`, `logo_path`, `modules` (json, model
  default `[]`); `PublicFirmResource` + `logo_url`, `is_platform`, `platform.name`; `auth/me` +
  `platform.name` (`UserResource::withPlatform`); `Firm/Services/MailBrand` (name, ink = primary,
  accent, logo, mailbox, the firm's own site) carried in `MeetingMailDTO`, `ReferralMailDTO`,
  `BriefingMailDTO` and read by the `mail.*` composer; every mail is from `mail.from.address` under
  the firm's name, reply-to its mailbox (D11); `config('rnc.brand')` removed; `MailPreviewController`
  takes `?firma=<slug>`. Consent: `platform_settings` + `PlatformSettings` (`consent_default`,
  write-once seed), `firm_consent_versions` + `FirmConsentVersion` + `ConsentTextService` (current,
  `startFirm`, `createVersion`, `forLead`, `contractConsent`); `ConsentTerms` keeps `VIA_*`, the
  generic `CONTRACT_TEXT`, `PLACEHOLDER`, `fill()`. Seeds: Franco short name + the landing's proof +
  v1 (C5); hub short name + default + v1; alvorada short name, tagline, proof + v1.
  Existing tests changed: `PublicFirmTest` (exact JSON), `PublicBrandTest` (main brand's own proof),
  `PublicDiagnosticsTest` and `BrandLeadTest` (Franco's v1 words, C5; sender `mail.from.address`,
  reply-to the mailbox), `ReferralsTest` (a literal stored text), `ContractReferralTest` and
  `RadarBatchReferralTest` (generic contract text filled), `PublicBookingTest` and
  `DailyBriefingCommandTest` (links on `franco.localhost`, C6), `PlatformSeedersTest` (seeders called
  through the container), `BriefingMailActionTest` (DTO's brand). `MailSenderTest` unchanged: it
  reads `.env`, and D11 keeps `MAIL_FROM_ADDRESS` as the sender.
- **F2b Runtime brand in both layouts — SPA** (A11, A12; R9, R10, R11) · 14/11 · full Vitest 1327
  (+16: `composables/brand/useFirmBrand.spec` 5, `useBrandTheme.spec` 7, `MNavbarBrand.spec` 2,
  `brandPalette.spec` +2 brands (franco, alvorada) and the 400 on the dark grounds; file moved) · Playwright: new
  `e2e/brand/two-palettes.spec.ts` 17 (2 firms × 2 schemes × landing/diagnostic/home/pipeline + the
  rail's names and title) and the touched `site/brands.spec.ts` 11, plus the adjacent folders theme,
  site, tenancy, diagnostic, navigation, pipeline, users, auth — 141, all green · Pest not run (no API
  change) · vue-tsc, aliases, ESLint, Prettier, sanitize, Pint, PHPStan, mirror check green ·
  pre-commit green 14/11/2026 without the full Playwright suite (F2c1 runs it; vue + design reviews
  inline: nothing left to fix).
  Red first: all 12 new composable cases and both `MNavbarBrand` cases failed before the code (modules
  absent); the new 400 assertions failed for express, prime, murky and franco on the old 400 (4.29:1 on Franco's row, dark canvas).
  Mutations seen red: the stored firm not read at boot (2 specs), the sub-brand painted with no public
  layout up, the rail block rendered before a firm is known, `useBrandTheme()` dropped from `App.vue`
  (4 e2e).
  What changed: `src/composables/brand/` — `useFirmBrand` (one `public/firm` per page load, no
  `X-Brand`, kept in `sessionStorage` `app.rnc:firm-brand` and read at boot; `isFirmMissing` moved here
  from `usePublicFirm`), `useBrandTheme` (mounted by `App.vue`: the firm's ramp on `<html>` for both
  layouts, `platform.name` as `document.title` off public pages) + `useSubBrandTheme` (`ThePublicLayout`:
  a sub-brand over the firm's only while a public layout is up), moved `brandPalette`, `publicBrand`, the
  `BRAND_*` constants; `PublicFirm` moved to `@Interfaces` (+ `is_platform`, `logo_url`, `platform`).
  The Site module re-exports all of it; `usePublicFirm` takes the main brand from `useFirmBrand` (one
  request, not two). `TheRouteGuard` reads `useFirmBrand`. `MNavbarBrand` (new molecule) in the rail and
  the drawer: firm tile + logo text + `platform.name`. `ThePublicLayout`'s mark writes the firm's
  `logo_text`. `hub.css` neutral slate theme (franco.css's shape), `VITE_ACTIVE_THEME` default `hub`
  (`vite.config.ts`, `theme.constant.ts`, `.env.example`, `.env.test` with its retired comment, this
  worktree's `app.rnc/.env`); `index.html` title "Carregando…". `brandPalette`'s 400 is lightened to
  read on the dark canvas/panel (C7).
  Existing tests changed: `TheRouteGuard.spec` (mocks `@Composables`' `useFirmBrand`), the two navbar
  organism specs (stub `MNavbarBrand`), `site/brands.spec.ts` (the host firm's header-less boot read is
  allowed beside the brand's; back on the main brand the root wears Franco's row, `data-brand=franco`).
- **F2c1 Scripted rename — role slug, routes, workspace and locale keys** (A13, A14; R14; AM6) · 14/11 ·
  scripted with `perl -pi` over `git ls-files api.rnc app.rnc` (CHANGELOGs excluded), tests first then
  code; per-pattern file counts in the commit body. Role `ceo` → `gestao` (seeders, Briefing's recipients,
  the bell's team roles, every test); `/reinaldo/*` → `/gestao/*`, `/ceo/*` → `/management/*`, route
  names `ceo-*` → `management-*`, `WORKSPACES`/rail/channel key `ceo` → `management`, locale keys
  `navigation.management` / `home.workspaces.management`; e-mail and briefing links → `/gestao`. New tests:
  `SeededRolesTest` "no `ceo` role" (A14), Workspace `routes.spec` 4 × "matches no route" for `/reinaldo`,
  `/ceo` and their briefing (A13). 14 units minor-bumped with a CHANGELOG line. **FULL GATE**: 1933 Pest ·
  1331 Vitest · 200 Playwright (4 flaky on the 1.1 h loaded run — diagnostic, navigation, site, users —
  green when re-run alone) · pint, PHPStan, vue-tsc, aliases, mirror check, lint/format/sort-locales ·
  convention reviews done inline (mechanical diff, no finding) · pre-commit green 14/11/2026.
- **F2c2 Franco/Reinaldo strings → the host firm; names guards** (A13, A15, A16; R14, R15; AM6) · 14/11 ·
  **FULL GATE**: 1938 Pest · 1345 Vitest · 204 Playwright (no flaky on the 4.1 min run; `site.spec` and `portal/access.spec` updated for the firm's `proof` and "Gestão", then re-run green) · pint, PHPStan, vue-tsc, aliases,
  ESLint, Prettier, sort-locales, mirror check green · convention reviews inline (vue, design, arcus):
  nothing left to fix · pre-commit green 14/11/2026.
  Red first: `NoTenantNamesTest` (the `nfe:import` help named `franco`), `SeededRolesTest` "Gestão",
  `noTenantNames.spec` (108 locale strings, `ALogo`'s default, `PROOF_POINTS`' "RNC"), `firmNames.spec`,
  the navItems "Gestão" case, `ALogo` "draws nothing", `MFirmLogo.spec` (absent), the `useBrandTheme`
  names case; `no-franco.spec.ts` 3 of 4 red against the old locales. Mutations seen red: `setFirmNames`
  dropped from `useBrandTheme`, `BRAND_QUERY.main` back to `franco`.
  What changed: locale values scripted (`locales.py` over `local/*.json`: 56 pt-BR, 54 en) —
  "Franco Contabilidade" → `{firmName}`, "Franco" → `{firmShort}`, "Reinaldo" → "Gestão"/"Management",
  `site.landing.proof.*` removed, `closingText` and `auth.brand.domain` generic. `libraries/i18n`:
  `withFirmPlaceholders` turns `{firmShort}`/`{firmName}` into linked messages `@:{'firm.short'}` /
  `@:{'firm.name'}` (braced: the bare form eats a following comma), `setFirmNames` (empty until a firm
  answers); `useBrandTheme` calls it with `short_name || name`. `ALogo` has no default name (draws
  nothing); new `MFirmLogo` (host firm's `logo_text`) on sign-in, forgot password, terms, not found,
  firm not found, repricing letter. Landing proof strip = firm's `proof` only (C10). `?marca=principal`
  or the host slug is the main brand (C9). API: `RoleSeeder` "Gestão" + generic descriptions,
  `nfe:import` help example `alvorada`; `phpunit.xml` gains the `Arch` suite. Units bumped: ALogo 3.0.0,
  MFirmLogo 1.0.0, composables, locales, Site, Repricing, Auth layouts/Terms/ForgotPassword, NotFound,
  FirmNotFound, e2e, database.
  Known gap recorded (Site RULES): the footer's credentials line is Franco's certifications.
- **F3a Modules per firm — API with degrade** (A17–A21; R16–R20) · 14/11 · full Pest 1985 (+47:
  `Unit/Modules/Firm/FirmModulesServiceTest` 13, `Firm/ModuleGateTest` 16, `Firm/ModuleCommandsTest` 4,
  `Briefing/BriefingDegradeTest` 4, `Portfolio/ContractDegradeTest` 3, `Radar/RadarDegradeTest` 4,
  `Auth/MeFirmTest` 3), all green · full Vitest 1345 (`--maxWorkers=2`; no app.rnc change) · Playwright
  not run (no app.rnc change, no e2e file added or edited — phase gate) · Pint, PHPStan, aliases,
  vue-tsc, ESLint, Prettier, sort-locales, mirror check, `wt-test` green; check-portal and check-cortex
  skip themselves here · arcus review inline (unit versions bumped, `based-on` with them on the
  rnc-origin units; nothing else to fix) · pre-commit green 14/11/2026 without the full Playwright
  suite. `make fresh-seed` clean: franco every key, alvorada every opt-in but `brands`, hub none.
  Red first: 41 of the 47 failed before the code (the six "while it is on" sanity cases passed).
  Mutations seen red (14, one per rule): `GET partners` ungated, `briefing:send` and
  `assistant:notify-expiring` walking every firm, the briefing's referrals and Radar action read
  regardless, the dependency check and the operator check on `channel` removed, the contract alert's
  referral fields kept, the Radar reading XMLs without `fiscal` and partners without `partners`, the
  chat start allowed, `X-Brand` read with `brands` off, `auth/me` without `firm`, the e-mail's
  referral block unguarded.
  What changed: Firm — `Helpers/FirmModules` (`CORE`, `OPT_IN`, `OPERATOR_ONLY`, `ALL`, `DEPENDENCIES`,
  `DISABLED`), `Services/FirmModulesService` (only writer; unknown 422, dependency 422, `channel` 403 for
  a non-operator), `CurrentFirm::hasModule()` / `forEachFirmWithModule()`,
  `FirmRepository::updateFirmModules()`, `Resources/FirmSummaryResource`; `app/Http/Middleware/
  EnsureModuleEnabled` (alias `module`, 404 `modules.disabled`, last on the route) on 60 routes of
  `api.local.php` (partners, portal, ledger, referrals, lead partner-matches/referrals → `partners`;
  repricing, client import, client timeline → `portfolio`; `fiscal`, `radar`, `contracts`, `assistant`,
  `briefing`, `brands`, `channel`; the Radar batch and the contracts' legal referral need `partners`
  too); `ResolveCurrentBrand` knows no sub-brand with `brands` off (C12); the diagnostic's chat start
  422 `modules.disabled` without `chat`. Degrade: `BriefingService` (`referrals` null, no fee range,
  no SLA/Radar action — C11), the briefing e-mail (no referral block, `preheader_no_referrals`),
  `RadarService` (no XML evidence nor key without `fiscal`, empty partner pickers without `partners`),
  `ContractService`/`ContractAlertResource` (no referral field without `partners`); `briefing:send`,
  `assistant:notify-expiring` skip firms without the module. `auth/me` + `firm` (null firm-less) and
  `platform: {name, is_operator, view_as}`. Seeds: `FirmSeeder` every key, `DemoFirmSeeder` D18's set,
  `PlatformFirmSeeder` none; `FirmFactory` every key (W32). Units bumped: Firm 1.6.0, Auth 2.12.0,
  Briefing 1.5.0, Radar 1.3.0, Portfolio 1.6.0, Diagnostic 1.3.0, Assistant 1.2.0, database 1.25.0,
  mail views 1.3.0. Known gap (Firm RULES): the SPA enforces nothing until F3c — the root's chat is
  refused on `hub`, which has no `chat`.
- **F3b1 `FirmProvisioner` + `firm:create`** (A22; R21, R13; D17; AM7) · 14/11 · full Pest 2002 (+17:
  `Firm/CreateFirmCommandTest` — firm + palette + core/`briefing`, six sector pages in order, consent
  v1 = platform default, `gestao` manager not a specialist, printed 16-char password refused on
  `franco` and signing in on `acme`, `--specialist` hours = business hours, refusals `www`/`hub`/
  `Acme!`/`-acme`/`franco`, taken username, taken/malformed e-mail, no name/manager, rollback), all
  green · full Vitest 1345 (`--maxWorkers=2`; no app.rnc change) · Playwright not run (no app.rnc
  change, no e2e file added or edited — phase gate) · check-app (aliases, vue-tsc), check-api (Pint,
  PHPStan), check-portal and check-cortex (skip themselves here), mirror check, sanitize (ESLint,
  Prettier, sort-locales) green · arcus review inline (units bumped; nothing else to fix) ·
  pre-commit green 14/11/2026 without the full Playwright suite. `make fresh-seed` clean (6 sector
  pages, 5 specialists, 25 hours — as before). Live: `php artisan firm:create acme --name="Acme
  Contábil" --manager=helena.acme --email=…` printed the password; through Vite's proxy on
  `acme.localhost:5173` (API on 8001, Pitfalls) `public/firm` named Acme, the login answered 200 and
  `auth/me` gave `helena.acme`, Gestão, firm `acme`, modules `['briefing']`; `firm:create www` exit 1
  `firms.slugReserved`.
  Red first: all 17 failed before the code (no command). Mutations seen red: the transaction dropped
  (rollback case), the reserved check off, the sector pages skipped, the modules write skipped,
  `--specialist` ignored, consent v1 skipped.
  What changed: Firm — `Services/FirmProvisioner`, `DTOs/CreateFirmDTO`, `DTOs/ProvisionedFirm`,
  `FirmRepository::createFirm()`; `app/Console/Commands/CreateFirm.php`. Content —
  `Helpers/DefaultSectorPages` (the six pages moved out of `SectorPageSeeder`),
  `SectorPageService::startFirm()`, `SectorPageRepository::upsertSectorPage()`. Booking —
  `Services/SpecialistService::makeBookable()`, `SpecialistRepository::upsertSpecialist()` /
  `replaceSpecialistHours()`. Auth — `UserStoreDTO` + `email`, `is_specialist` (no form rule, so the
  form never sends them), `UserService::isEmailAvailable()`, `RoleService::MANAGER_SLUG`. Seeders
  `SectorPageSeeder` and `SpecialistSeeder` now go through those services (rows unchanged). Units
  bumped: Firm 1.7.0, Content 1.1.0, Booking 1.5.0, Auth DTOs 1.5.0, Auth Services 2.8.0, database
  1.26.0.
- **F3b2 Seeders per firm + the demo firm + `gestao.hub`** (A23; R22, R23; D18; AM7, AM3's moved check) ·
  14/11 · full Pest 2009 (+7: `Seed/DemoFirmSeedTest` — the local seed gives Alvorada ≥ 40 leads, ≥ 3
  meetings, referrals naming Alvorada not Franco, traffic, ≥ 200 clients, contracts, NF-e, served notes,
  every row in its own firm, Franco's counts unchanged; Alvorada's four people, two specialists with
  hours, `parceiro.alvorada.<n>` logins; contact data not Franco's, `hub` the one platform firm with no
  module and its `gestao.hub` manager; a module off seeds none of its data; `PlatformManagerSeeder`
  refuses testing/production/staging ×3), all green · full Vitest 1345 (`--maxWorkers=2`; no app.rnc
  change) · Playwright not run (no app.rnc change, no e2e file added or edited — phase gate) ·
  check-app, check-api (Pint, PHPStan), check-portal and check-cortex (skip themselves here), mirror
  check, sanitize green · arcus review inline (database unit 1.27.0 + its rule, Franco-only docblocks
  generalised; nothing else to fix) · pre-commit green 14/11/2026 without the full Playwright suite.
  `make fresh-seed` clean in 14.6 s: franco 40 leads · 14 meetings · 8 partners/12 referrals · 757
  visitors · 500 clients · 40 contracts · 2629 NF-e · 10 notes; alvorada 40 · 14 · 8/12 · 757 · 320 ·
  40 · 2287 · 10, 6 sector pages; hub `gestao.hub` only.
  Red first: all 6 first tests failed before the code (no Alvorada data, no team, no
  `PlatformManagerSeeder`). Mutations seen red: the module gate in `forEachDemoFirm` off (Alvorada got 8
  partners with `modules` empty), `PlatformManagerSeeder`'s `local` gate off (5 red, `SeedShapeTest`'s
  exact list included). The demo data stays out of the Pest seed by construction: only `DemoFirmSeeder`
  (local only) makes Alvorada, and the per-firm seeders seed the firms that exist.
  Live (AM3's moved check, W46): through Vite's proxy on `alvorada.localhost:5173` (API on 8001)
  `public/firm` named Alvorada; a diagnostic start answered `visitante` with "Autorizo Alvorada
  Contabilidade…"; `helena.alvorada` and `equipe.alvorada` saw 41 leads (visitante 6), `patricia` on
  `franco.localhost` 40 (visitante 5) — the new lead in Alvorada's board only; `gestao.hub` signed in at
  `localhost:5173`, Gestão, firm `hub`.
  What changed: `database/seeders/Concerns/SeedsEachDemoFirm` (`forEachDemoFirm` with a module skip and
  the firm's ordinal, `demoFirms`, `teamOf`, `managerOf`); `PipelineSeeder` (team by role, names shifted
  13 for another firm), `PartnerSeeder` (`partners`; message "A <short name> indica…"),
  `PartnerUserSeeder` (`partners`; `parceiro.<slug>.<n>` / `portal.<slug>.<n>@` off Franco),
  `MetricsSeeder`, `ClientSeeder` (`portfolio`; Alvorada 320 from seeds +100), `ContractSeeder`
  (`contracts`), `NfeSeeder` (`fiscal`; one folder and one line per firm), `AssistantNoteSeeder`
  (`assistant`; Franco's usernames → the firm's stand-ins by role); `DemoFirmSeeder` + `equipe.alvorada`,
  `camila.alvorada`, its six sector pages; `SpecialistSeeder` profiles for Alvorada's two; new
  `PlatformManagerSeeder` (local only) in `seeders.local.php`. Database unit 1.27.0.
- **F3c Modules per firm — SPA** (A24, A25; R17, R19, R20) · 14/11 · full Vitest 1391 (`--maxWorkers=2`;
  +46: `User/store.spec` 3, `attachAuthGuard.helper.spec` 4, `navItems.local.spec` 10,
  `libraries/router/routes.local.spec` 20 new, `BriefingView` A25, `RadarView`, `ContractsView`, `LeadView` 2,
  `DiagnosticView.chatOff.spec` 2), all green · full Pest 2011 (+2: `PublicFirmTest` modules, `MeFirmTest`
  sign-in answer), all green · Playwright: the phase's files `tenancy/modules.spec.ts` (new, 4) and
  `diagnostic/chat.spec.ts` (edited) 10/10, plus — because `fixtures/auth.ts` changed — every folder
  whose screens are opt-in (briefing, radar, contracts, navigation, pipeline, portal, channel,
  assistant, fiscal, repricing, brand, tenancy, diagnostic): 113 tests, 112 green and 1 timeout (`diagnostic.spec.ts` quiz walk, a slot button 'not stable' under load) that passed 8/8 when its file ran alone with `--retries=0`; the rest of the suite not run
  (phase gate) · check-app (aliases, vue-tsc), check-api (Pint, PHPStan), mirror check, sanitize
  (ESLint, Prettier, sort-locales) green; check-portal and check-cortex skip themselves here ·
  vue-conventions, design-conventions and arcus reviews inline (computed order in `DiagnosticView`
  fixed; nothing else) · pre-commit green 14/11/2026 without the full Playwright suite.
  Red first: 38 of the new/changed Vitest cases and the 3 new Pest assertions failed before the code
  (the Diagnostic chat specs that only wait for the toggle, and the "while on" cases, passed).
  Mutations seen red: the guard's module check off (3 unit + 3 e2e red), the rail's `onlyEnabled`
  filter off (e2e), the rail's `partners` entries unmarked (2), the Contracts picker asked regardless (1).
  Live (worktree API on 8001, `make fresh-seed`, Alvorada's `partners` off by tinker, then restored):
  `public/firm` with `X-Firm: alvorada` listed `[portfolio, fiscal, radar, contracts, briefing]`;
  `helena.alvorada`'s sign-in answered `firm.modules` the same and `platform`; `GET partners` 404
  `modules.disabled`, `GET leads` 200; the root's `public/firm` `is_platform` true, `modules` []. The rail
  and the not-found screen were proven in Playwright on the mocked session (W52), not in Chrome
  (verify's rounds).
  What changed: SPA — `@Enums` `FIRM_MODULE`; `User` store `firm`/`platform` + `hasModule` (kept when a
  payload has no `firm` key); `attachAuthGuard` `meta.module` → not-found at the same address, after the
  permission, and `meta.moduleFallback` on the redirect it came from; `routes.local.ts` `ofModule()` marks
  Partner/Portal (`partners`), Repricing (`portfolio`), Fiscal, Radar, Contracts, Assistant, Briefing,
  Brands, Channel; Workspace `management-home` falls back to the metrics; `navItems.local.ts` entries carry
  `module`, `onlyEnabled` filters them (TheNavbar untouched); Briefing draws no referral tile/panel when
  `referrals` is null; Radar and Contracts take `canRefer` (no batch / no legal referral, no picker
  request, no "enviados" tile); `LeadView` no `MLeadRouting` without `partners` (W38); the diagnostic's chat
  toggle only with `chat`. API — `PublicFirmResource` `modules`; `AuthController::login` and
  `LoginCodeController::verify` answer `withPlatform()`. Unit versions bumped (User 2.2.0, helpers 1.15.0,
  TheNavbar 1.9.0, Briefing/Radar/Contracts 1.2.0, Pipeline 1.5.0, Diagnostic 1.4.0, Home 3.6.0, e2e 1.19.0,
  api Firm 1.8.0, Auth 2.13.0). Spec C13, C14.
- **F4a Platform operator and console — API** (A26–A28; R11, R24, R25; AM1) · 14/11 · **SECURITY-SENSITIVE:
  flagged for the verify review (P6)** · full Pest 2118 (+107: `Platform/OperatorSignInTest` 7,
  `Platform/PlatformFirmsTest` 22, `Platform/ConsentDefaultTest` 7, `Access/Phase4AccessTest` 51 + the route
  matrix's 7 new rows; `RoleLoginTest` lost its `battoni` row — moved to `OperatorSignInTest` — and `SeedShapeTest`
  expects `battoni` as the one firm-less account), all green · full Vitest 1391 (`--maxWorkers=2`; only
  locale JSON changed in app.rnc) · Playwright not run (no e2e file added or edited — phase gate) · check-app
  (aliases, vue-tsc), check-api (Pint, PHPStan), mirror check, sanitize (ESLint, Prettier, sort-locales)
  green; check-portal and check-cortex skip themselves here · arcus review inline (vue/design reviews not
  applicable: no Vue/TS file changed; nothing to fix) · pre-commit green 14/11/2026 without the full
  Playwright suite.
  Red first: the cross-firm negative tests were written first; 77 of the 87 new tests failed before the code
  (the 3 "unknown id → 404" and the 7 "operator passes" rows held only because the routes did not exist — the
  latter tightened to also refuse 404). Mutations seen red: `platform.operator` dropped from the routes (14 red:
  a firm account with the key, a firm-placed `operador`), `RoleService`'s operator-only guard off (2), the lead
  count scoped by `FirmScope` (1), `provideManager`'s firm check off (1 — another firm's manager reset), the
  `{escritorio}` check off (2).
  Live (worktree API on 8001, `make fresh-seed`, HTTP with a cookie jar, W56): `battoni` at the root → `firm`
  null, `is_operator` true; with `X-Firm: franco` 422; `platform/firms` hub 0 · alvorada 40 · franco 40 leads
  with their modules; `GET leads` as the operator 0 (hub's); `POST platform/firms` ipe → `http://ipe.localhost:5173`
  + `gestao.ipe` and a password; primary `#7fd3ff` 422 `brand.lowContrast`; `gestao.ipe` signed in with
  `X-Firm: ipe` (firm ipe) and got 403 on `platform/firms`. Re-seeded after.
  P6 security list (for verify): the console's lock is `auth` + `permission:platform.*` + `EnsurePlatformOperator`
  (role `operador` AND `firm_id` null) on all 7 routes (`routes/api.local.php`); `RoleService::refuseOperatorOnlyKeys`
  refuses `platform.*` to any non-administrator role; the firm a console route touches is chosen by the route id
  in `PlatformFirmRepository::findFirmById` (404 `firms.notFound`); `FirmScope` lifted only in
  `PlatformFirmRepository::countLeadsByFirm`/`countLeadsOfFirm` (counts); a login is refused/reset in
  `FirmProvisioner::provideManager` (only the firm's own `gestao` is reset, sessions deleted; any other
  username 422, untouched); a new manager's firm is stamped by `UserService::create` under `CurrentFirm::runAs`.
  The operator's own firm stays `ResolveCurrentFirm`'s (AM1, unchanged): platform firm, `X-Firm` → 403.
  What changed: `SuperAdminSeeder` (`battoni` firm null); Auth `permission-groups.local.php` (`platform` group:
  `platform.firms`, `platform.consent`), `RoleService` (`OPERATOR_ONLY_KEY_PREFIX`, 422
  `rolesAndPermissions.operatorOnlyKey`), `UserService::resetPassword`/`findByUsername`; middleware
  `EnsurePlatformOperator` (alias `platform.operator`, 403 `platform.operatorOnly`); Firm: `FirmProvisioner::provideManager`
  + race catch (Known Gap retired), `FirmLookService`, `UpdateFirmLookDTO`, `FirmManagerDTO`, `ManagerLogin`,
  `Rules/ReadableUnderWhite` (shared with `UpdateBrandRequest`), `PlatformSettings::updateConsentDefault`,
  `MailBrand::siteUrlOf` public; new module `app/Modules/Platform` (controllers, requests, service, repository,
  resources, RULES.md with the AM1 Known Gap, CHANGELOG 1.0.0); locales: the roles screen's `platform` group,
  `consent` label and `operatorOnlyKey`. Versions: Auth 2.14.0, Firm 1.9.0, database 1.28.0, locales 1.14.0,
  Platform 1.0.0. Spec C15.
- **F4b Operator console — SPA** (A29, A30; R24, R25) · 14/11 · **FULL GATE**: full Pest 2118 (no API code
  changed; only the Platform module's RULES/CHANGELOG), all green · full Vitest 1424 (`--maxWorkers=2`; +33:
  `MAddEditFirmForm.spec` 11, `MFirmModulesForm.spec` 4, `MFirmManagerForm.spec` 4,
  `FirmsView.view.integration.spec` 6 (A29), `ConsentDefaultView.view.integration.spec` 5, `navItems.local.spec`
  +1 (and 2 extended), `HomeView.spec` +2), all green · full Playwright 210 (+2: `platform/console.spec.ts`, A30),
  all green, none flaky (`test-results/.last-run.json` passed, no retry artifacts) · check-app (aliases 34 in all
  three, vue-tsc), check-api (Pint, PHPStan), mirror check, wt-test, sanitize (ESLint, Prettier, sort-locales)
  green; check-portal and check-cortex skip themselves here · vue-conventions, design-conventions and arcus reviews
  inline (fixed: `FirmsView`'s one-line computeds split by a blank line, the subdomain link given `touch-target`;
  arcus: only the API Platform RULES Known Gap retired) · pre-commit green 14/11/2026 — every step ran.
  Red first: every new spec failed before the code (the Platform specs on missing files; the rail's 3 platform
  cases and HomeView's operator redirect on assertions); the HomeView "keeps a person with a firm" case and the
  rail's existing cases held, as they should. Mutations seen red: the modules form's dependency block off (1), the
  new firm not prepended (1), the look validation skipped (1), the operator redirect off (e2e A30 red on the URL).
  Live (worktree API on 8001, HTTP with a cookie jar, W60): `battoni` at the root → `firm` null, `is_operator`
  true; `POST platform/firms` "Ipê Contábil"/`ipe` → `http://ipe.localhost:5173`, `gestao.ipe`, 16-char password;
  `POST …/manager` `gestao.ipe` → reset (`created` false); `gestao.ipe` signed in with `X-Firm: ipe` (firm ipe,
  role gestao) and got 403 on `platform/firms`. Re-seeded after (`migrate:fresh --seed`).
  What changed (app.rnc): new module `src/modules/Platform` (constants, interfaces, 7 services, routes
  `/plataforma/escritorios` + `/platform/firms`, `/plataforma/consentimento` + `/platform/consent`, molecules
  `MAddEditFirmForm`, `MFirmModulesForm`, `MFirmManagerForm`, `MOneTimePassword`, views `FirmsView`,
  `ConsentDefaultView`, RULES 1.0.0, CHANGELOG); `@PlatformModule` alias (vite, vitest, tsconfig; config-rules
  1.5.0); `routes.local.ts`; rail "Plataforma" in `navItems.local.ts` (TheNavbar 1.10.0); `HomeView` sends the
  firm-less operator to the console (Home 3.7.0, HomeView 1.2.0); locales `platform` group + 3 `navigation` keys,
  `sort-locales.mjs` registers `platform` (locales 1.15.0); e2e 1.20.0. api.rnc: Platform RULES 1.0.1 (Known Gap
  "No SPA yet" retired). Spec: no C# (W57–W59 stay inside R25's wording).
- **F6a Beta signup — API** (A31; R29, D29) · 14/11 · `tests/Feature/Lead/BetaSignupTest.php` 30 tests (new).
  Gate: Pest dirs Lead, Notification, Access, Pipeline, Firm, Mail, Arch 698 + Diagnostic, Metrics,
  `MailSenderTest`, `DtoValidatedInputTest` 111, all green; Pint, PHPStan, mirror check green (`make check-api`,
  `make sync-mirror-check`); arcus review inline (1 finding fixed: Lead RULES `based-on` bumped with `version`).
  Phase gate green 14/11/2026 — NOT the full `/battoni-pre-commit`: skipped `check-app`, `sanitize`, Vitest and
  Playwright (no app.rnc file changed, no e2e file in this phase; plan Defaults/D30), `check-portal` (no portal),
  `check-cortex` (skipped by design here).
  Red first: 24 of the 29 first tests failed before the code (the 5 tenant-404 cases held with no route).
  Mutations seen red: `platform.firm` off the POST (1: the "404 before validation" case), BOTH the middleware and
  `BetaSignupService`'s guard off (4: the tenant/default/`X-Firm: hub`/consent-read 404s), consent `accepted` →
  `boolean` (1), throttle off (1), `mailTeam` off (1), bell off (1), consent words not from `ConsentTextService` (1),
  token born live (1), UF not upper-cased (7), band label not translated in the e-mail (1). Not caught alone: the
  service guard with the middleware on (defence in depth).
  Live (worktree API on 8001, W67): `GET public/beta-signups/consent` at the root → hub's v1 words; POST header-less
  201, with `X-Firm: franco` 404, with `X-Firm: hub` 404; one `beta_signup` bell row (`gestao.hub`) and the
  e-mail in `laravel.log`. The local DB keeps that one `beta` lead ("Contábil Teste") on hub — handy for F6b's chip.
  What changed: Lead (1.9.0) `LeadChannel::Beta`, `BetaClientsBand`, `PublicBetaSignupController`,
  `BetaSignupRequest`, `BetaSignupDTO`, `BetaSignupService`, `LeadService::recordBetaSignup`,
  `ConsentTextService::forFirm`, `PublicBetaSignupResource`, `Mail/BetaSignupMail` + `BetaSignupMailDTO`, Known Gap
  "no dedupe"; Notification (1.5.0) `BetaSignupNotification` (`beta_signup`), `TeamNotifier::mailTeam`; middleware
  `EnsurePlatformFirm` (`platform.firm`, `bootstrap/app.php`); routes `POST public/beta-signups`
  (`throttle:5,1,beta-signup`, `platform.firm`, `brand`) and `GET public/beta-signups/consent` (`routeClasses.php`:
  both public); mail views 1.4.0 `beta-signup` (+ text), `mail.beta_signup.*` pt_BR/en, `/mail-preview/beta-signup`.
  Spec: no C# (W61–W66 stay inside R29/D29).
- **F6b Product site on the root — SPA** (A32–A34; R28, R29; W66 fixed) · 14/11 · Vitest full 1446 (199 files), all
  green — new: `ProductLandingView.view.integration.spec` 8, `MBetaSignupForm.spec` 7, `productSite.spec` 5, `MLeadCard`
  +1 (Beta chip), `MNotificationBell` +1 (`beta_signup`); Playwright `e2e/product/landing.spec.ts` 11 (new) + the dirs the
  shell and the routes touch (`e2e/site`, `brand`, `diagnostic`, `tenancy`): 78 run, all green; Pest Firm, Lead, Platform,
  Access 679 (+2 in `PlatformSeedersTest`), all green. Gate: `make check-app` (aliases + vue-tsc), `make check-api` (Pint
  `--test` + PHPStan), `check-portal` (no portal — skipped by the target), `sync-mirror-check`, `sanitize` (lint, format,
  sort-locales), `wt-test` (128) green; vue/design/arcus reviews inline (1 finding fixed: the band pick moved out of the
  template into `onBandPick`). Phase gate green 14/11/2026 — NOT the full `/battoni-pre-commit`: skipped the full
  Playwright suite and the full Pest suite (plan Defaults/D30: touched dirs only), `check-cortex` (skipped by design here).
  Red first: the 3 new Vitest files failed on import and the chip/bell cases on the missing key before the code; the API's
  2 seeder cases failed before `BETA_CONSENT`. The e2e file was written after the components (W74). Mutations seen red:
  `loadHomeView`/`firmSiteOnly` always platform (3), consent check off in the form (1), the seeder's "only at version 1"
  guard off (2), the hero lede in `text-muted` (the e2e contrast sweep, 1.21:1 light / 2.53:1 dark).
  Live (worktree API on 8001 after `migrate:fresh --seed`, HTTP with cookie jars, W73): `GET public/beta-signups/consent`
  at the root → v2, the beta words; `POST public/beta-signups` 201; `gestao.hub`'s `GET leads` has the lead with channel
  `beta`; `reinaldo`'s (franco, 40 cards) has none. The local DB keeps that lead ("Contábil Ao Vivo") on hub.
  What changed: Site (1.8.0) `ProductLandingView`, organisms `OProductHero`, `OProductUrgency` (W70), `OProductHowItWorks`,
  `OProductModules`, `OProductBeta`, `OProductFaq`, molecule `MBetaSignupForm`, services `GetBetaConsentService`,
  `SignUpForBetaService`, `productSite` (`loadHomeView`, `firmSiteOnly`, `scrollToSection`, `useProductSite`), constants
  `PRODUCT_*`, `BETA_MESSAGE_MAX_LENGTH`, enum `BETA_CLIENTS_BAND`, locales `site.product.*` (pt-BR + en);
  `ThePublicLayout` product mode (nav, CTA, footer); `/` route → `loadHomeView` (W68), `/reforma/:slug` and Diagnostic
  (1.5.0) `/diagnostico` → `firmSiteOnly` (W69); Pipeline (1.6.0) `LEAD_CHANNEL.BETA` + chip; Notification (1.3.0)
  `beta_signup`; helpers (1.16.0) `firmSiteUrl`; env `DEMO_FIRM_SLUG` (`VITE_DEMO_FIRM_SLUG=alvorada` in `.env.example`,
  `.env.test` and this worktree's `.env`); API database (1.29.0) `PlatformFirmSeeder::BETA_CONSENT` as hub's v2 (W71).
  Spec: no C# (W68–W74 stay inside R28/R29/D23/D24).
- **F5a Firm settings — API** (A35–A38; R30–R33; AM2; W31 closed) · 14/11 · full Pest 2233 (new: `Firm/FirmSettingsTest` 16,
  `Auth/FirmUsersTest` 19, `Booking/SpecialistHoursTest` 15, `Lead/ConsentVersionEndpointTest` 9; `SeededRolesTest` +1; the route
  matrix's cases for 7 new routes), all green · Vitest full 1448 (`--maxWorkers=2`; +2: `ThePublicLayout.spec` credentials case,
  `ThePublicLayout.noCredentials.spec`), all green · Playwright `e2e/site` 25 (the footer's dir; no e2e file added or edited),
  all green · `make check-app` (aliases 34, vue-tsc), `make check-api` (Pint, PHPStan), `check-portal` (no portal, skipped by the
  target), `sync-mirror-check`, `sanitize` (lint, format, sort-locales) green; vue, design and arcus reviews inline (fixed: the
  settings request's three `max:255` folded into `TEXT_MAX_LENGTH`, a redundant comment in `ThePublicLayout` dropped). Phase gate
  green 14/11/2026 — NOT the full `/battoni-pre-commit`: skipped the full Playwright suite (plan Defaults/D30; only the touched
  `e2e/site` dir ran) and `check-cortex` (skipped by design here).
  Red first (negative tests written first, security): 52 of the first run's 69 cases (the four new files + `SeededRolesTest`) failed before the code (the guest 401s, the
  team's 403s on `auth/users`, and AM2's 404s on another firm's/the operator's account already held from F1a — as they should);
  both new layout cases failed before the footer change. Mutations seen red: `TeamRole` off (2), the create's firm stamp nulled
  (4), the bookable filter off (1), a re-flag resetting hours (1), the update's flag ignored (1), the look in the settings answer
  (1), `firm.settings` off the routes (2), hours' closing-after-opening rule off (1), the consent author dropped (2), the
  `{escritorio}` rule off (1), `credentials` off `public/firm` (2), the consent history unscoped (2).
  P6-style list (user management is a privilege boundary): a role is refused in `AssignableRole` (key cap) AND `TeamRole`
  (`operador`/`parceiro` from any firm member); `firm_id`/`partner_id` have no rule (never reach `UserService`); every user read by
  id goes through `findFirmUserById` (F1a); the settings, consent and hours routes take no id but the specialist's, and work on
  `CurrentFirm` (`FirmScope` 404s another firm's specialist).
  Live (worktree API on 8001, HTTP with a cookie jar, W80): `helena.alvorada` → `GET firm/settings` alvorada, credentials null;
  `PATCH` credentials + `brand_primary` → `public/firm` shows the line, the colour unchanged; consent v2 201 with Helena as author,
  history [2, 1]; `POST auth/users` with role `operador` 422 (`cannotAssignUnheldRole` + `roleNotAssignable`); `GET specialists`
  Bruno, Camila; `X-Firm: franco` 403. Re-seeded after (`make fresh-seed`).
  What changed: Firm (1.10.0) `FirmSettingsController` (`GET/PATCH firm/settings`), `UpdateFirmSettingsRequest`,
  `UpdateFirmContactDTO`, `FirmSettingsResource`, `FirmSettingsService::current`/`updateContact`, `firms.credentials` (+ `Firm`,
  `PublicFirmResource`); Auth (2.15.0) `Rules/TeamRole`, `is_specialist` on the user requests/DTOs, `UserService` starts a newly
  flagged specialist through Booking, `ResolvesActorPermissions::actorIsPlatformOperator`, permission group `firm` (`firm.settings`);
  Booking (1.6.0) `SpecialistHoursController` (`GET specialists`, `GET/PUT specialists/{id}/hours`), request, DTO (`#[Present]`),
  `SpecialistResource`, `SpecialistHourResource`, `SpecialistService::startOnFirmHours`/`list`/`find`/`updateHours`, bookable =
  flagged; Lead (1.10.0) `ConsentVersionController` (`GET/POST firm/consent-versions`), `StoreConsentVersionRequest`,
  `StoreConsentVersionDTO`, `ConsentVersionResource`, `ConsentTextService::historyOfCurrentFirm`/`createForCurrentFirm`,
  `FirmConsentVersion::author()`; database (1.30.0) `create_firms` + `credentials`, `FirmSeeder::CREDENTIALS`,
  `RoleSeeder::GESTAO_FIRM_KEYS`; routes in `api.local.php` (7, `routeClasses.php`: staff). app.rnc: `ThePublicLayout` reads
  `firm.credentials` (no block when null, none on the product site), `PublicFirm.credentials`, `site.footer.credentialsText` removed,
  `rolesAndPermissions.groups.firm` (locales 1.16.0); Site (1.8.1) Known Gap retired; `helpers/RULES.md` table re-padded by Prettier
  (sanitize). Spec C16, C17.
- **F5b Firm settings — SPA** (A39, A40; R30–R33) · 14/11 · Vitest full 1477 (`--maxWorkers=2`; +29 in FirmSettings:
  `FirmContactView` 5, `FirmTeamView` 6, `FirmSpecialistsView` 6, `FirmConsentView` 7, `MAddEditTeamMemberForm` 5; `navItems.local.spec`
  2 cases extended), all green · Playwright `e2e/firm-settings/settings.spec.ts` 3 (new) + `e2e/navigation` + `e2e/users` = 28, all green,
  stable over 3 runs with `--retries=0` · Pest not run (no api.rnc change) · `make check-app` (aliases 35, vue-tsc), `make check-api`
  (Pint, PHPStan), `sync-mirror-check`, `sanitize` (lint, format, sort-locales) green; vue, design reviews inline (fixed: watchers moved
  to named handlers, a `window` identifier shadowing the global renamed `span`, ref order, weekday toggles `gap-3`). Phase gate green
  14/11/2026 — NOT the full `/battoni-pre-commit`: skipped the full Playwright suite (plan Defaults/D30; only this phase's e2e file plus
  `navigation`/`users`), the full Pest suite (no API change), `check-portal` (no portal) and `check-cortex` (skipped by design here);
  arcus review not run (no PHP touched).
  Red first: the e2e file was written before the components and failed (3/3); the 5 Vitest files failed on import and the 2 rail cases
  on the missing entries before the code. Mutations seen red: preview not filled (1), `{escritorio}` check off (1), every role offered
  (1), new password not shown (1), reset sending more than the password (1), hours weekday check off (1), the specialist's saved week
  not put in its row (1), a 480px team list (e2e overflow 125px — see Pitfalls: first measured green on the document alone).
  Live (worktree API on 8001, Python cookie jar, W82): `helena.alvorada` → consent history [1], POST v2 201 author Helena, a text without
  `{escritorio}` 422 `leads.consentPlaceholderMissing`; `auth/roles` readable; `POST auth/users` (equipe, specialist, a browser-style
  16-char password) 201 on alvorada, the new login signs in with it; role `parceiro` 422 `users.form.roleNotAssignable`; `GET specialists`
  lists the new person; reset 200; archive → inactive. Re-seeded after (`make fresh-seed`). "A new diagnostic stores v2, an old lead keeps
  v1" stays Pest-proven (F5a A38), not re-run live.
  What changed: new module FirmSettings (1.0.0) — views `FirmContactView`, `FirmTeamView`, `FirmSpecialistsView`, `FirmConsentView`;
  molecules `MBusinessHoursEditor`, `MAddEditTeamMemberForm`, `MTeamPasswordForm`, `MSpecialistHoursForm`; composables
  `useOneTimePassword` (W81), `useApiRefusal`; 8 services; routes `/gestao/escritorio|equipe|especialistas|consentimento` (+ en
  `/management/firm|team|specialists|consent`); rail: four children at the end of "Gestão" behind `firm.settings` (TheNavbar 1.11.0, W83);
  locales (1.17.0) `firmSettings.*`, `navigation.firm*`, `users.form.roleNotAssignable`, `leads.consentPlaceholderMissing`; config (1.6.0)
  `@FirmSettingsModule` alias, `primevue/radiobutton` in `optimizeDeps`; e2e (1.21.0). Spec: no C# (W81–W83 stay inside R30–R33).
## Next
1. `/battoni-start-session rnc-4` (or the running `/battoni-run rnc-4`) → **F4c** Logo upload and view-as — API (A41, A42; R26, R27;
   security-sensitive, P6 list in its Done line). Read plan.md → F4c.
## Pitfalls
- (F1a) `UserFactory` now puts every user in the default firm (`franco` under phpunit), creating it
  when absent. A test that creates `Firm::factory(['slug' => 'franco'])` AFTER a user would collide
  on the unique slug — create firms first. A test meaning "no firm" passes `'firm_id' => null`.
- (F1a) A signed-in user with no firm and no platform firm row → 403 `firms.required`; a test with
  a firm-less operator creates `hub` (`is_platform`) itself.
- (F1a) A signed-in user sending `X-Firm` of another firm is now 403 (D5): a test that sets
  `withHeader` keeps it for every later request in the same test — `flushHeaders()` between actors.
- (F1a) Sanctum's stateful list is inert in this API (sessions start in the api group, guard `web`):
  an unlisted Origin is not a 401 (W4). Host-only cookies and CORS are what separate hosts.
- (plan) `phpunit.xml` pins `DEFAULT_FIRM_SLUG=franco` (D6): a test about the platform firm must
  create `hub` itself and send no `X-Firm`.
- (plan) From F1b, `localhost:5173` is the PLATFORM firm, not Franco: Franco is
  `franco.localhost:5173`. Never use `127.0.0.1` — cookies and Sanctum's stateful list are per host.
- (plan) The SPA reaches the API only through Vite's `/api` proxy (D4); a direct `localhost:8000`
  call from a firm host carries no session.
- (F1b) The e2e suite runs on `franco.localhost` and `TheRouteGuard` asks `GET public/firm` on every
  hard navigation there: `seedAuthSession` answers it; a spec that starts without that fixture and
  counts console errors must mock `**/api/public/firm**` itself.
- (F1b) A spec about another host swaps only the hostname of `baseURL` (`tenancy/firm-host.spec.ts`).
- (F1b) The main checkout's `php artisan serve` held port 8000 during F1b and stopping it was refused
  by the permission layer: the live check served this worktree's API on 8001 with
  `VITE_API_PROXY_TARGET=http://localhost:8001 npm run dev`. Stop the main stack before `make serve-*`.
- (F2a) `public/firm` now answers Franco's `short_name` and `proof` (no longer null): the landing's
  proof strip reads the firm's four points; Franco's `tagline` stays null because `tagline` drives
  both the landing kicker and the footer line (W17). F2b/F2c decide the SPA side.
- (F2a) A firm with no consent version gets v1 from the platform default the first time it is read
  (`ConsentTextService::currentVersion`): a `GET`-shaped answer (`PublicLeadResource`) can write that
  row. A test about "no version yet" checks the table before any diagnostic call.
- (F2a) A mail DTO needs a `MailBrand` (`MailBrand::fromFirm($firm, $brand)` or `::platform()`);
  links in e-mails are on `<slug>.localhost:5173` (C6), so a test asserting a URL names the firm host.
- (F2a) A seeder with dependencies in `run()` is called `app()->call([app(X::class), 'run'])` or
  `$this->seed(X::class)`, never `->run()` bare.
- (F2b) The firm's palette is painted from `public/firm` at runtime on every page; the theme file is
  `hub` (neutral slate). An e2e spec that mocks no `public/firm` measures the hub ramp — or, if the main
  checkout's API holds :8000, Franco's real row through the test server's proxy (seen in
  `theme/contrast.spec.ts` on `/login`). Measure contrast with a mocked firm, never by accident.
- (F2b) `.p-button` is `position: relative` and outvotes Tailwind's `absolute`: the drawer's close
  button is a flex item, so a `w-full` sibling squeezed it to 38px (touch-chrome spec). Siblings there
  take `min-w-0 flex-1`.
- (F2b) Under heavy machine load (load average 55 during F2b, other runs alongside) the full Vitest
  run times out a different 3–4 unrelated specs each time; `npx vitest run --maxWorkers=2` ran
  1327/1327 green. A failure that moves between runs is the load, not the code.
- (F2b) `useFirmBrand` reads `sessionStorage` at module load: a Vitest spec about it calls
  `vi.resetModules()` and imports it fresh, and clears `sessionStorage` in `beforeEach`.
- (plan) `battoni` signs in only at the root (no firm since F4a).
  _Retired 14/11/2026 (F4a):_ "`battoni` belongs to Franco until F4a" — moved.
- (F2c1) The role is `gestao` and the paths are `/gestao/*` (`/management/*`); `/reinaldo` and `/ceo`
  match no route. A local DB seeded before F2c1 still holds a `ceo` role: `make fresh-seed`.
  _Retired 14/11/2026 (F2c1):_ "The role is `ceo` until F2c renames it" — renamed.
- (F2c1) A scripted `\bceo-` also matches `$ceo->` in PHP — two files had to be put back; a later bulk
  rename anchors on what follows the token, not only a word boundary.
- (F2c1) The full Playwright run took 1.1 h under machine load with 4 flaky specs passing on retry;
  re-run the flaky files alone with `--retries=0` before calling one red.
- (F2c2) A locale string that needs the host firm writes `{firmShort}` or `{firmName}`, never `{firm}`:
  `{firm}` is a parameter the caller passes (portal, repricing letter). Vitest's `setup.ts` fills the
  host names with Franco; a spec about another firm calls `setFirmNames` itself.
- (F2c2) `ALogo` with no `name` draws nothing — use `MFirmLogo` for the host firm's mark.
- (F2c2) `src/test/noTenantNames.spec.ts` and `tests/Arch/NoTenantNamesTest.php` fail on any
  Franco/Reinaldo/Willy/APW outside comments, specs and `test/` folders, and on "RNC" outside the
  Channel module (locales: outside a `channel` key).
- (F3a) `FirmFactory` gives a firm EVERY module (`channel`, `brands` included); the model's default is
  none. A test about a module being off sets `modules` itself (`forceFill(['modules' => …])->save()`),
  and a firm built with `new Firm` or `Firm::create` has none.
- (F3a) The platform firm `hub` is seeded with no module: a firm-less operator (from F4a) or any root
  visitor gets 404 `modules.disabled` on every opt-in route, and the root's chat start 422 — expected,
  not a bug, until F3c hides them.
- (F3a) `module:<key>` is the LAST middleware on a route: a guest still gets 401 and a wrong role 403
  before the module's 404. A new opt-in route appends `->middleware('module:<key>')`, and
  `routes/api.local.php`'s header says which key.
- (F3a) Writing `firms.modules` goes through `FirmModulesService::set($firm, $all, $actor)` — the WHOLE
  opt-in list, not a delta; core keys are dropped, `channel` changes need the operator.
- (F3b1) A firm is made only by `FirmProvisioner` (`firm:create`, F4a's console): it validates first,
  then writes in one transaction. Sector pages, consent, users and specialist hours go through
  their own module's service (`SectorPageService::startFirm`, `ConsentTextService::startFirm`,
  `UserService::create`, `SpecialistService::makeBookable`) — F3b2's seeders reuse those, not copies.
- (F3b1) The SPA sends `X-Firm` from its host; a curl through Vite's proxy on `acme.localhost:5173`
  without that header lands on the platform firm (`hub`), not acme. Send `X-Firm: <slug>` as the SPA does.
- (F3b2) A demo seeder runs once per seeded firm through `Concerns\SeedsEachDemoFirm::forEachDemoFirm`
  (Franco, then Alvorada when it exists); a new one names its module so a firm without it is skipped,
  takes people from the firm's roles, and puts the firm's slug in any global key (usernames, e-mails).
- (F3b2) `DemoFirmSeedTest` runs the whole seed as `local` with the NF-e disk faked — `NfeSeeder`
  deletes `nfe/<firm id>/` first, and an unfaked disk would wipe a developer's seeded files. ~5 s a case.
- (F3b2) `make fresh-seed` now takes ~15 s (two firms' NF-e, ~4,900 XMLs).
- (F3c) A signed-in Vitest spec of a screen with an opt-in part gives the user a `firm` with its modules
  (`setUserAndPermissions({ ...user, firm: { modules, … } })`); without one `hasModule` is false and the
  part is gone. A payload without the `firm` key keeps the store's firm — `clearUser` or a new pinia resets it.
- (F3c) Every e2e spec that spreads `mockUser` gets Franco with every module (`fixtures/auth.ts`); a spec
  that builds its own user from scratch, or its own `public/firm`, must list the modules its screen needs.
- (F3c) A pt-BR address in an English e2e run is rewritten to its English twin before the guard runs:
  assert `/gestao/...` with `seedLanguage(page, 'pt-BR')`.
- (F3c) `public/firm` is read once per page load and kept (`useFirmBrand`): a Vitest spec that needs a
  firm WITHOUT a module in a file whose other cases had it mocks `usePublicFirm` in its own file
  (`DiagnosticView.chatOff.spec.ts`).
- (F4a) `battoni` has no firm: it signs in only at the root (`localhost:5173`, no `X-Firm`); on
  `franco.localhost` its login is the wrong-password 422. A test about the operator creates `hub`
  (`PlatformFirmSeeder`) and sets `firms.default_slug` to `hub`; `RoleLoginTest` no longer lists it.
- (F4a) A console route takes its firm from the route id, never `CurrentFirm`; the operator's own requests
  stay in `hub`. A 403 body carries the key under `error`, not `message` (`assertJsonPath('error', …)`).
- (F4a) A role can never hold a `platform.*` key except `operador` — a seeder or test granting one to
  another role through `RoleService` gets 422; `UserPermission` overrides are still refused at the route.
- (F4a) In this worktree's sandbox a Bash call mixing heredocs, `cp`, `sed -i` and git in one line is
  refused as "too complex"; write files with Write and run one plain command per call.
- (F3c) A Bash `python3 /dev/stdin file <<EOF` edit silently did nothing here — write the script to the
  scratchpad and run it by path.
- (F4b) In this sandbox `source ~/.nvm/nvm.sh` is refused; run Node 24 as
  `PATH=/Users/battoni/.nvm/versions/node/v24.19.0/bin:$PATH rtk vitest …` (or `rtk make e2e`).
- (F4b) `rtk make e2e` keeps only a truncated log of a green run; the outcome is
  `app.rnc/test-results/.last-run.json` (`status`, `failedTests`).
- (F4b) An e2e firm list mocked with two rows of the same `id` collapses in the `v-for` and the new-firm
  dialog's password never shows — give every mocked firm its own id.
- (F4b) The main checkout's `php artisan serve` still held :8000; the live check used :8001 again.
- (F6a) The beta routes 404 on every tenant AND on `X-Firm: hub` (AM9): the SPA's beta form must call them from
  the root host WITHOUT `X-Firm` — `ApiProvider` adds it only when the host has a slug (`FIRM_SLUG`), so the root is fine.
- (F6a) The beta form's consent words are the platform firm's current version — since F6b hub's v2, the beta words
  (`PlatformFirmSeeder::BETA_CONSENT`, W71); a test that counts hub's versions expects two after the seed.
  _Retired 14/11/2026 (F6b):_ "today the generic partner-introduction sentence" — the seeder now writes the beta's own.
- (F6a) A test that rings hub's team creates hub's people with `assistantPerson('gestao', firm: $hub)`;
  `PlatformManagerSeeder` (`gestao.hub`) is local-only.
- (F6b) `/` resolves its view from `useFirmBrand().load()` (`loadHomeView`): a spec or e2e at the root must answer
  `public/firm` with `is_platform: true`, or it gets the firm landing. A firm-host e2e that mocks no `public/firm` still
  gets the firm landing (a failed read is a firm).
- (F6b) PrimeVue's `Select` picks an option on `mousedown`: a Vitest spec fires `fireEvent.mouseDown(option)`; Playwright's
  real `click()` works as is.
- (F6b) A contrast helper that treats any colour ending in ", 0)" as transparent misreads an accent with no blue
  (`#f2a900` → `rgb(242, 169, 0)`): read the alpha (`e2e/product/landing.spec.ts` `alphaOf`). `e2e/site/brands.spec.ts`'s
  `ground()` has the same pattern and only works because no brand there has a zero blue.
- (F6b) Repeated logins in a live script hit the login throttle (429) a few accounts in — space them or log each account
  in once.
- (F5a) A specialist is bookable only while its person is flagged `is_specialist`: a test that builds a `Specialist` row for a
  person created without the flag gets no slots and no row in `GET specialists` — `assistantPerson(..., specialist: true)`.
- (F5a) `PATCH auth/users/{id}` with `is_specialist: true` makes the person bookable on the firm's hours only the first time; a
  person with a row (even switched off) keeps its old hours.
- (F5a) In this sandbox `curl` through `rtk` fails and a `php artisan serve &` inside a script never answered; the live check ran
  the server as a background task and a Python `urllib` script with a cookie jar on `127.0.0.1:8001` (API only — the SPA rule P1
  is about the browser).
- (F5b) `TheLayout` scrolls the back office in `.main-scroll` (`overflow-y-auto`, so x as well): a row wider than the screen scrolls
  THERE while `document.documentElement` stays the window's width. An e2e "no horizontal scroll" check measures both
  (`e2e/firm-settings/settings.spec.ts` `horizontalOverflow`); `platform/console.spec.ts` still measures only the document.
- (F5b) `auth/users` takes the password from the caller and has no generate route: the SPA draws it (`useOneTimePassword`); a live
  script that creates a login must send one.
- (F5b) A new PrimeVue component in a template must join `optimizeDeps.include` in `vite.config.ts` (`src/test/optimizeDeps.spec.ts`).
## QA
(none yet — rounds in plan.md → QA)
## Seeded people (password `seeding.password`; `battoni` uses `seeding.superadmin_password`)
- Platform: `battoni` — operador, no firm (since F4a), root only (`localhost:5173`).
- Platform firm `hub` (F1a): `gestao.hub` — gestao, local only (`PlatformManagerSeeder`, F3b2).
- Franco (`franco`, unchanged from rnc-3): `reinaldo` (ceo → `gestao` in F2c), `patricia` (equipe),
  specialists `mariana.toledo`, `carlos.prado`, `juliana.reis`, `rafael.antunes`; partners
  `parceiro.1`…`parceiro.8`.
- Demo firm Alvorada (`alvorada`, invented, local only; full since F3b2): `helena.alvorada` (gestao),
  `equipe.alvorada` (equipe, Lívia Fontana), specialists `bruno.alvorada`, `camila.alvorada`; partners
  `parceiro.alvorada.1`…`parceiro.alvorada.8`.
## Local environment
- API `make serve-api` → http://localhost:8000; SPA `make serve-app` → http://localhost:5173 (root =
  platform firm), http://franco.localhost:5173, http://alvorada.localhost:5173; `make fresh-seed`
  rebuilds `api.rnc/database/database.sqlite`. Main checkout's stack stopped while this worktree serves.
- API `.env` needs `DEFAULT_FIRM_SLUG=hub`, `PLATFORM_NAME`, `PLATFORM_ROOT_DOMAIN=localhost`,
  `REGISTRATION_ENABLED=false` and `SANCTUM_STATEFUL_DOMAINS=localhost:5173,*.localhost:5173` —
  F1a wrote them to `.env.example` AND to this worktree's `api.rnc/.env` (gitignored; AM4). The
  worktree's SQLite was rebuilt (`migrate:fresh --seed`): firms franco, hub (platform), alvorada.
- Mail goes to `api.rnc/storage/logs/laravel.log` (`MAIL_MAILER=log`).
## Away run
Stop at: draft PR after verify · Reviewer: fable-reviewer (#fable session) · Chrome QA: on
Started: 2026-11-13 23:58
00:00 F1a started, attempt 1
F1a done
23:39 F1b started, attempt 1
F1b done
00:02 F2a started, attempt 1
F2a done
00:25 F2b started, attempt 1
F2b done
02:08 F2c1 started, attempt 1
F2c1 done
04:32 F2c2 started, attempt 1
F2c2 done
08:15 F3a started, attempt 1
F3a done
08:38 F3b1 started, attempt 1
F3b1 done
08:53 F3b2 started, attempt 1
F3b2 done
09:08 F3c started, attempt 1
F3c done
09:45 F4a started, attempt 1
F4a done
10:08 F4b started, attempt 1
F4b done
10:38 F6a started, attempt 1
F6a done
10:52 F6b started, attempt 1
F6b done
11:27 F5a started, attempt 1
F5a done
11:57 F5b started, attempt 1
F5b done
## Decided while you were away
- W1 plan: proposal.html skipped in away mode (spec D27; to reverse: `/battoni-new-proposal rnc-4 final`).
- W2 plan: the owner's phases are split into 17 builder phases with his labels kept (spec D28); his
  "F4b logo + view-as" is F4c/F4d because the console itself needed F4a (API) + F4b (SPA) (to reverse: n/a).
- W3 plan: human QA round off; Chrome rounds by role only (plan.md → QA; to reverse: `/battoni-new-qa-session rnc-4`).
- W4 F1a: AM4's "an unlisted origin returns 401" cannot hold — the API never runs Sanctum's
  stateful middleware (the api group starts the session; guard `web`), so the stateful list gates
  nothing. Not the "wildcard fails" stop: the list carries `*.localhost:5173` and the positive half
  holds (login on `franco.localhost:5173` then `auth/me` 200). `StatefulHostTest` asserts instead that
  CORS never grants an unlisted origin. To reverse: put `EnsureFrontendRequestsAreStateful` in the
  api group and give every test an Origin.
- W5 F1a: `UserFactory` defaults `firm_id` to the default firm (creating it) — AM1 + AM2 broke 105
  existing tests whose users had no firm; the alternative (a global `hub` row in every test) moved
  Franco's tests to the platform firm. To reverse: `'firm_id' => null` in the factory and give each
  test an explicit firm.
- W6 F1a: registration closed by a 404 middleware on routes that stay registered, not by not
  registering them (spec C1). To reverse: conditional `Route::post`.
- W7 F1a: QA login-as reads the firm from `X-Firm`, else the Host — a browser navigation sends no
  header (spec C2). To reverse: header only.
- W8 F1a: `POST auth/users` stamps the current firm (spec C3), pulled forward from F5a. To reverse: n/a.
- W9 F1a: a firm-less signed-in person sending ANY `X-Firm` gets 403 `firms.mismatch` (R5's "never
  falls through to the header", read with AM1). To reverse: ignore the header for a firm-less session.
- W10 F1b: funnel events on a firm host go by keepalive `fetch` with `X-Firm`, not the beacon (no
  headers on a beacon; spec C4). To reverse: beacon everywhere plus a firm field in the body.
- W11 F1b: the firm-not-found page is swapped in by `TheRouteGuard` (no route, the address stays), and
  the route renders while the firm answer is pending — a missing firm then replaces it. To reverse:
  hold the slot until `public/firm` answers (one round-trip on every hard navigation).
- W12 F1b: AM3's live check ran as HTTP calls through Vite's proxy with a cookie jar per host, not in
  Chrome (Chrome rounds are verify's, plan.md → QA); API on 8001 (Pitfalls). To reverse: n/a.
- W13 F1b: `seedAuthSession` answers `public/firm` with Franco by default — 8 back-office e2e tests
  that count console errors went red on the refused boot read. To reverse: mock it in each spec.
- W14 F2a: Franco's consent v1 is its rnc-1 sentence with `{escritorio}` where "Franco" was, filled
  with the full name — the main brand now reads "Autorizo a Franco Contabilidade…", Express/Prime
  unchanged (spec C5). A literal sentence would drop the sub-brand's name. To reverse: fill the main
  brand with `short_name`, or seed the literal sentence.
- W15 F2a: queued mails carry a `MailBrand` value object in their DTO, not a firm id resolved at
  render with `withoutGlobalScope` (plan text) — the values-not-models pattern AM6 already set; the
  worker resolves nothing. To reverse: carry `firmId` and resolve it in the composer.
- W16 F2a: e-mail links (report, briefing, footer) go to the firm's own host `<slug>.<root>` (spec
  C6) — a root link opened the platform firm, where the lead is absent. To reverse:
  `config('rnc.frontend_url')` in `MailBrand::siteUrlOf`.
- W17 F2a: Franco's `tagline` seeded null (the site keeps its own kicker and footer line, which one
  `tagline` would replace both of); its `proof` is the landing's four points, identical on screen. To
  reverse: a `tagline` in `FirmSeeder`.
- W18 F2a: under a sub-brand `logo_url` is null and the e-mail shows the initial tile, not the firm's
  logo — the brand has no logo and the firm's would contradict its name. To reverse: drop the
  `! $brand` guard in `PublicFirmResource` and `MailBrand::fromFirm`.
- W19 F2a: a firm with no consent version gets v1 from the platform default lazily on first need, as
  a safety net beside the seeders and `firm:create` (F3b1). To reverse: answer without consent words
  (or 409) until a version exists.
- W20 F2b: the host firm's read is its own composable (`useFirmBrand`, no `X-Brand`), and
  `usePublicFirm` reuses its answer on the main brand — the plan's single "boot fetch" read under a
  sub-brand would have put Express's colours on the back office. To reverse: one read carrying
  `X-Brand` for both layouts.
- W21 F2b: the "logo text" test is `MNavbarBrand.spec.ts`, not `TheLayout.spec.ts` — the rail's block
  is a new molecule shared by rail and drawer, and `TheLayout` stubs the navbar. To reverse: render
  the name in `TheLayout` itself.
- W22 F2b: off public pages `document.title` is `platform.name` alone; a public page keeps `useSeo`'s
  "page · firm". `index.html` reads "Carregando…" until then. To reverse: "firm · platform" in the
  back office.
- W23 F2b: `brandPalette`'s 400 is lightened until it reads on the dark canvas and panel (spec C7) —
  `celer-button-link-primary` measured 4.29:1 on Franco's runtime row. To reverse: the plain mix and
  a back-office override of the dark link.
- W24 F2c1: identifiers that only spell the old name are kept — `RoleSeeder::CEO_EXTRA_KEYS`,
  `BriefingRepository::CEO_ROLE`/`findActiveCeosOfFirm`, PHP `$ceo` variables, the test helpers'
  `$this->ceo` — AM6 limits the script to the slug, routes and keys, and a blind `ceo` → `gestao` over
  identifiers reaches past them. Prose naming the SLUG in comments and RULES.md moved with it. To
  reverse: rename the identifiers in F2c2.
- W25 F2c1: the old paths `/reinaldo/*` and `/ceo/*` are dropped with no redirect (A13 asks "not
  found"); a bookmark to `/reinaldo` lands on Not Found. To reverse: redirect routes from the old paths.
- W26 F2c2: the host firm's placeholders are `{firmShort}` / `{firmName}`, not `{firm}` (spec C8) —
  `{firm}` was the portal's and letter's own parameter. To reverse: rename those first.
- W27 F2c2: `?marca=principal` or the host firm's slug drops the sub-brand (spec C9). To reverse:
  `BRAND_QUERY.main = 'franco'`.
- W28 F2c2: a firm with no `proof` shows no proof strip (spec C10). To reverse: generic i18n fallback.
- W29 F2c2: the screens that drew `ALogo`'s default name draw `MFirmLogo` (a molecule reading
  `useFirmBrand`, like `MNavbarBrand`) rather than each passing the firm. To reverse: a prop per screen.
- W30 F2c2: `RoleSeeder` keeps `firstOrCreate`, so a database seeded before keeps "CEO"/"Reinaldo" labels
  until `make fresh-seed`; W24's identifiers (`CEO_EXTRA_KEYS`, `$ceo`) still kept. To reverse:
  `updateOrCreate` on name/description.
- W31 F2c2: the footer's credentials line (Franco's certifications) stays static copy — it names no
  tenant; recorded as a Site Known Gap for the console phases. To reverse: a firm field now.
- W32 F3a: `FirmFactory` defaults `modules` to every key (the model's default stays none) — with an
  empty default every existing test of a gated route would have needed its firm's modules set.
  To reverse: drop the factory default and give each test its modules.
- W33 F3a: the plan's `FirmModules::FRANCO_ONLY` is `OPERATOR_ONLY` — the names guard (F2c2) refuses a
  tenant's name in `app/`, and R18 frames `channel` as the operator's to set. To reverse: rename.
- W34 F3a: `auth/me`'s `firm.modules` lists the opt-in keys only (core is always on, never listed); the
  brand summary is flat (`short_name`, `logo_text`, `logo_url`, `brand_primary`, `brand_accent`,
  `is_platform`, like `public/firm`); `view_as` is `{slug, name}` or null. A19 is proven on
  `FirmModulesService` (unit) — no route writes modules before F4a's console. To reverse: list core too.
- W35 F3a: without `portfolio` the briefing keeps its pipeline counts and nulls only the fee ranges;
  `contracts` has no briefing action to drop (spec C11). To reverse: `pipeline: null`.
- W36 F3a: a gated route answers 404 `modules.disabled` (not a bare 404) and the module check runs
  after `auth` and the key. To reverse: put `module:` first, or answer `notFound` with no key.
- W37 F3a: with `brands` off every `X-Brand` is unknown (404 `brands.notFound`; the beacon falls back)
  (spec C12). To reverse: ignore the header.
- W38 F3a: the client timeline (`clients/{id}/events`, under `fiscal.read`) and the lead detail's
  partner matches and referral are gated by their owner — `portfolio` and `partners` — not by the screen
  that calls them; F3c must stop the lead detail asking for matches without `partners`. To reverse:
  `module:fiscal` on the timeline, leave the lead routes ungated.
- W39 F3b1: a command line has no operator, so `firm:create` writes the modules with the NEW MANAGER as
  `FirmModulesService::set`'s actor (`briefing` is not operator-only); the console (F4a) passes its
  operator. To reverse: a system actor, or an operator-only check that skips the CLI.
- W40 F3b1: `--email` is optional and becomes the firm's mailbox too; the manager's first/last name
  come from the username (`helena.acme` → Helena / Acme); a new firm's hours are weekdays 09–18 (the
  platform firm's); the password is 16 letters and digits (`Str::password` without symbols, so it
  copies from a terminal). To reverse: require `--email`, add `--manager-name`, symbols on.
- W41 F3b1: the six default sector pages moved to Content's `Helpers/DefaultSectorPages`, and the
  specialist hours to Booking's `SpecialistService` — the provisioner writes other modules' tables
  only through their services (arcus rule), and `SectorPageSeeder` / `SpecialistSeeder` now use the
  same paths (rows unchanged). Refusal keys: new `firms.slugInvalid` / `slugReserved` / `slugTaken` /
  `nameRequired`, `users.form.usernameRequired` / `emailInvalid` / `emailTaken`, reused
  `users.form.usernameTaken`; the SPA locales get them with F4a's console. To reverse: inline writes.
- W42 F3b2: the demo seeders loop the seeded firms through one trait (`SeedsEachDemoFirm`) and Alvorada
  reuses Franco's invented lists — board names shifted 13, portfolio and contracts from seeds +100
  (320 clients, its landing's "300+"), the same eight "(exemplo)" partners and ten notes — rather than
  lists of its own. To reverse: per-firm constant lists in each seeder.
- W43 F3b2: `gestao.hub` lives in a new local-only `PlatformManagerSeeder`, not `LocalUsersSeeder` (which
  runs under `testing` and would grow `SeedShapeTest`'s exact list, AM7). To reverse: move it there and
  add it to that list.
- W44 F3b2: in Alvorada the knowledge base's people are stand-ins by role — Helena edits, the first team
  member asks, Bruno/Camila review and answer in turn for Franco's four specialists; `equipe.alvorada`
  is "Lívia Fontana". To reverse: name Alvorada's people in `AssistantNoteSeeder`.
- W45 F3b2: F3b's "`make fresh-seed` prints the people" is not added — the seed prints its seeders and
  NfeSeeder's per-firm counts; the people stay in this file's table. To reverse: a summary line at the
  end of `seeders.local.php`.
- W46 F3b2: AM3's moved check ran as HTTP calls through Vite's proxy with a cookie jar per host (as
  W12), API on 8001; Chrome rounds stay verify's. To reverse: n/a.
- W47 F3c: `public/firm` answers the firm's whole opt-in list (`modules`, as `auth/me`), not a
  `has_chat` flag — the public site may need `brands` or `partners` next, and which modules a firm has is
  no secret worth a second shape (spec C13). To reverse: a flag per public need.
- W48 F3c: the sign-in answers (`auth/login`, `auth/phone/verify`) carry `firm` and `platform` like
  `auth/me` (spec C13) — without them the rail after a fresh login had no modules until a reload. To
  reverse: a `fetchMe()` after every sign-in in the SPA.
- W49 F3c: `meta.module` is set in `routes.local.ts` (`ofModule`) for whole route sets, not in each
  module's route files (plan text) — one place shows the split, and a route file stays plain data. To
  reverse: a `meta.module` on each route record.
- W50 F3c: `/gestao` (`/management`) lands on the metrics while `briefing` is off, through
  `meta.moduleFallback` on the redirect routes, instead of not-found (spec C14). To reverse: drop the meta.
- W51 F3c: the rail is filtered in `navItems.local.ts` (LOCAL), not in the synced `TheNavbar`'s
  `isVisibleNavItem`; the portal entry is `partners`', repricing `portfolio`'s. Without `partners` the Radar
  draws no batch column and the Contracts no legal referral and no "enviados ao jurídico" tile (W38's lead
  routing gone too). To reverse: a `module` field on the core `NavItem` and a TheNavbar check.
- W52 F3c: A24's live check ran as HTTP calls on the worktree API (8001) plus a Playwright spec on a mocked
  session for the rail and the not-found screen; Chrome rounds stay verify's (as W12, W46). To reverse: n/a.
- W53 F4a: the console's routes need the key AND the firm-less `operador` (`EnsurePlatformOperator`), and
  `RoleService` refuses `platform.*` to any other role — the plan named only the keys; with the key alone a
  per-user override or a firm-placed `operador` would open the console. To reverse: drop `platform.operator`
  from the routes and `refuseOperatorOnlyKeys`.
- W54 F4a: `POST platform/firms/{id}/manager` creates OR resets by username — the firm's own `gestao` is reset
  (sessions ended, 200), a free username is created there (201), any other account (another firm's, or a member
  here who is not the manager) is 422 `users.form.usernameTaken` and untouched. To reverse: two routes, or
  reset any account of the firm.
- W55 F4a: the default consent must contain `{escritorio}` (422 `platform.consentPlaceholderMissing`, ≤ 1000
  characters) — spec C15. To reverse: drop `namesTheFirm` from `UpdateConsentDefaultRequest`.
- W56 F4a: the live check ran as HTTP calls on the worktree API (8001) with a cookie jar, not in Chrome — no
  screen in this phase; Chrome rounds stay verify's (as W12, W46, W52). To reverse: n/a.
- W57 F4b: one `MAddEditFirmForm` with two modes — create asks only what `POST platform/firms` takes (name,
  slug with its address preview, manager username, optional e-mail) and the look is edited from the row after;
  the new firm joins the list at once while the dialog keeps the one-time password on screen. The plan listed
  look fields on the same form; the API's create takes none. To reverse: a create that chains a `PATCH`.
- W58 F4b: the manager login is a form molecule `MFirmManagerForm` in `MMainDialog` (plan: `MManagerLoginDialog`),
  per celer-07's form contract; the password component `MOneTimePassword` is shared with the create. To reverse:
  rename.
- W59 F4b: the operator's landing is `HomeView` replacing itself with the console when `platform.is_operator`, no
  `firm` AND the key `platform.firms` — the key guards against a redirect loop if the console were refused. To
  reverse: drop the key from the check.
- W60 F4b: the live check ran as HTTP calls on the worktree API (8001) with a cookie jar, plus the A30 Playwright
  spec on a mocked API; Chrome rounds stay verify's (as W56). To reverse: n/a.
- W61 F6a: a `GET public/beta-signups/consent` (`{text, version}`, platform firm only) was added beside the POST —
  F6b's form shows "the API's text" before any lead exists, and the diagnostic's way (`PublicLeadResource`) needs a
  lead. The POST answers `{company, channel}` (an allow-list for the success state). To reverse: drop the GET and
  put the words in `public/firm` for the platform firm.
- W62 F6a: "hub's users with `leads.read`" is read as `TeamNotifier`'s team — active `equipe` and `gestao`, the two
  roles granted `leads.read` — for both bell and e-mail (`TeamNotifier::mailTeam`, one copy per member); a per-user
  `leads.read` override and the firm-less operator are not rung. To reverse: query users by effective permission.
- W63 F6a: a tenant gets 404 from a new `platform.firm` middleware BEFORE the body is validated (a firm host never
  sees a 422 that betrays the route), and `BetaSignupService` refuses again. The throttle runs first, so a tenant's
  calls count too. To reverse: the service's null alone (a tenant would then get 422 on a bad body).
- W64 F6a: the beta lead's timeline gets the existing `contato.capturado` `{consent}` line (no new event type the
  SPA would have to learn); its token is born expired like a portfolio lead's; `source`/`medium` are optional
  from the site (default `direto`), as on the diagnostic. To reverse: a `beta.inscricao` event type.
- W65 F6a: the form's fields are fixed as `company, name, role, email, phone, city, uf, clients_band, message?`
  with four clients bands (`ate_100`, `de_101_a_300`, `de_301_a_1000`, `acima_1000`) and UF from the 27 units; phone
  required (normalised to `55…`), message ≤ 1000. To reverse: change `BetaSignupRequest`/`BetaClientsBand`.
- W66 F6a: the consent stored and shown is the platform firm's current version as R29 says — today the generic
  partner-introduction sentence, which reads oddly for a beta; the operator rewrites it in the console (F4a) and a
  new version applies. Not a hard stop. To reverse: a beta-specific consent text (spec change).
- W67 F6a: the live check ran as HTTP calls on the worktree API (8001), no screen in this phase; Chrome rounds stay
  verify's (as W56). To reverse: n/a.
- W68 F6b: the `/` route picks its view from the host firm's `is_platform` (the boot read) in a lazy loader
  (`loadHomeView`), instead of the plan's "picked by the public layout"; the layout follows the same flag for its nav, CTA
  and footer (`useProductSite`). A firm that could not be read gets the firm landing (an outage must not turn a firm's
  site into the product's). To reverse: one view at `/` switching inside `ThePublicLayout`.
- W69 F6b: R28's "the diagnostic never shows at the root" is a `beforeEnter` (`firmSiteOnly`) on `/diagnostico` and on
  `/reforma/:slug` (a firm's sector pages), landing on `/`; `/relatorio/:token` stays open (it is reached by a token in an
  e-mail). To reverse: drop the `beforeEnter`s.
- W70 F6b: the landing gained an `OProductUrgency` organism (three dated facts, the law, the day checked, the CBS's
  requested delay as news) beside the plan's five; the module menu leaves out `channel` (one network's, operator-only);
  the demo link opens in a new tab. To reverse: fold the facts into the hero; add `channel` to `PRODUCT_OPT_IN_MODULES`.
- W71 F6b: W66 fixed — `PlatformFirmSeeder` writes hub's consent v2 = `BETA_CONSENT` ("Autorizo a equipe do {escritorio}
  a entrar em contato comigo e com o meu escritório … sobre o programa beta … conforme a LGPD"), only while hub is still at
  version 1, so a re-seed never stacks a version on the operator's own words; the platform default (what new firms copy)
  is untouched. To reverse: drop the block; the operator writes the words in the console.
- W72 F6b: on a 422 the beta form shows its own per-field sentence for each field the API names, never the API's message
  (the API answers in its own locale and prose); a 429 says to wait a minute; other failures say to try again. To reverse:
  show `errors.<field>[0]` through `translateError`.
- W73 F6b: the live check ran as HTTP calls on the worktree API (8001) with cookie jars — consent v2, signup 201, the lead
  on `gestao.hub`'s board with channel `beta`, not on `reinaldo`'s; `helena.alvorada`'s login hit the login throttle (429)
  after the run's earlier logins, so alvorada's board was not read live (firm scoping is Pest-proven since F1a). The
  Chrome rounds (QA round 1) stay verify's (as W56). To reverse: n/a.
- W74 F6b: `e2e/product/landing.spec.ts` was written after the components, so it was not seen red before the code; its
  contrast sweep was proven by a mutation instead (hero lede in `text-muted` → 1.21:1, red). The Vitest and Pest files
  were red first. To reverse: n/a.
- W75 F5a: the credentials line (W31) is a nullable `firms.credentials` string, answered by `public/firm` and edited by the manager
  in `PATCH firm/settings` (not the operator's look form); Franco's line comes from `FirmSeeder::CREDENTIALS`, every other firm starts
  with none and its footer drops the "Credenciais" block; the product site shows none. The line is the firm's own words in one
  language — the English site prints it as written (spec C16). To reverse: a look field in the console, or keep a static fallback.
- W76 F5a: a firm member can never give `operador` or `parceiro`, even holding every key of the role (`Rules\TeamRole`, 422
  `users.form.roleNotAssignable`), on create AND update — AM2 named only `operador`; a partner login is the partner screen's, made
  with its partner. Only the firm-less operator passes. To reverse: drop `TeamRole` and rely on `AssignableRole`'s key cap.
- W77 F5a: the manager gets `users.read|create|update|archive|reset_password` but NOT `users.delete` (deactivate is R31's word) and
  not `users.search` (no route uses it). To reverse: add the keys to `RoleSeeder::GESTAO_FIRM_KEYS`.
- W78 F5a: "a newly flagged specialist starts on the firm's hours" — flagging (create or `PATCH is_specialist: true`) makes the row
  through `SpecialistService::startOnFirmHours`; a person who already had a row keeps its hours. Switching the flag off keeps the row
  and hides the person from the agenda and the hours list (bookable = flagged), so its hours and past meetings survive (spec C17).
  A `GET specialists` list was added beside the plan's `GET/PUT specialists/{id}/hours` (the hours screen needs the people). To
  reverse: delete the row on unflag; drop the list.
- W79 F5a: a new consent text must hold `{escritorio}` (422 `leads.consentPlaceholderMissing`, ≤ 1000) like the platform default
  (C15); the history answers the stored words unfilled (the SPA previews them) with `author {id, name}` or null. To reverse: accept
  any non-empty text.
- W80 F5a: the live check ran as HTTP calls on the worktree API (8001) with a cookie jar — no screen in this phase; Chrome rounds stay
  verify's (as W56). To reverse: n/a.
- W81 F5b: a team login's password (new or reset) is drawn in the browser — 16 letters and digits without look-alikes from
  `crypto.getRandomValues` (`useOneTimePassword`) — sent once and shown once with `MOneTimePassword`; `auth/users` takes the password
  from the caller and F5a added no generate route. The reset sends the password alone. To reverse: a server-side generate route that
  answers the password, as the console's manager login does.
- W82 F5b: the live check ran as HTTP calls on the worktree API (8001) with a cookie jar, plus the A40 Playwright spec on a mocked API;
  the Chrome rounds (QA round 3) stay verify's (as W56). The plan's "a new diagnostic stores v2, an old lead keeps v1" is F5a's Pest
  (A38), not re-run live. To reverse: n/a.
- W83 F5b: the four settings screens are the last children of "Gestão" in the rail, all behind `firm.settings` (the team's route asks
  `users.read` like its API; inside it each `users.*` key hides its own button); the role is two radio choices (Equipe, Gestão) read from
  `auth/roles` by slug, not a select; the consent form also refuses saving the current version's exact words. To reverse: a separate
  "Escritório" group; a select; allow an identical version.
