# State: Reforma hub as a white-label platform for accounting firms
Stage: build F1a/19
Updated: 13/11/2026
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
## Next
1. `/battoni-start-session rnc-4` (or the running `/battoni-run rnc-4`) → **F1b** Firm by host — SPA
   and local hosts (plan.md F1b + AM3, AM4 SPA half, AM5: full Vitest and full Playwright). Update
   the worktree's `app.rnc/.env` with `.env.example`'s new keys (AM4).
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
- (plan) `battoni` belongs to Franco until F4a moves it to no firm; from F4a it signs in only at the root.
- (plan) The role is `ceo` until F2c renames it `gestao`; seeds written before F2c use `ceo`.
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
