# State: Reforma hub as a white-label platform for accounting firms
Stage: build F2c2/19
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
## Next
1. `/battoni-start-session rnc-4` (or the running `/battoni-run rnc-4`) → **F3a** Modules per firm — API
   with degrade (A17–A21).
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
- (plan) `battoni` belongs to Franco until F4a moves it to no firm; from F4a it signs in only at the root.
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
## QA
(none yet — rounds in plan.md → QA)
## Seeded people (password `seeding.password`; `battoni` uses `seeding.superadmin_password`)
- Platform: `battoni` — operador (firm `franco` until F4a, then no firm, root only).
- Platform firm `hub` (F1a; manager from F3b): `gestao.hub`.
- Franco (`franco`, unchanged from rnc-3): `reinaldo` (ceo → `gestao` in F2c), `patricia` (equipe),
  specialists `mariana.toledo`, `carlos.prado`, `juliana.reis`, `rafael.antunes`; partners
  `parceiro.1`…`parceiro.8`.
- Demo firm Alvorada (`alvorada`, invented; F1a minimal, F3b full): `helena.alvorada` (manager),
  `equipe.alvorada` (team), specialists `bruno.alvorada`, `camila.alvorada`; partners
  `parceiro.alvorada.1`… (F3b).
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
