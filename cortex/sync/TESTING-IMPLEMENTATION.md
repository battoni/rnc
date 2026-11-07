# Testing Implementation Plan (point Sonnet here)

Fresh, self-contained plan reflecting all strategy decisions. Supersedes the task list in
`TESTING-HANDOFF.md`. Read `TESTING-STRATEGY.md` first for the *why*; this is the *do*.

## Working agreement

- **Human runs tests in the terminal** and pastes results; fix from feedback. Don't loop on
  a flaky tool channel.
- **Never commit unrun tests.** Green run confirmed → commit.
- **Ground every test in REAL code** — read the file first; never assume an API.
- **Per-unit version bump:** any unit whose tests change → new CHANGELOG top entry + matching
  RULES `version` (SemVer, `SCHEMA.md`). For arcus (tests live in `tests/`), bump the
  mirrored source unit's CHANGELOG.
- **Stage by explicit path**, never `git add -A` (avoid sweeping parallel work).
- **Commit per batch**, conventional message.

## Verified state (2026-05-30, from disk)

**Committed:** `4c32653` Vitest infra (`vitest.config.ts`, `src/test/setup.ts`,
`src/test/mount.ts`, `bodyScrollLock.helper.spec.ts`).

**Uncommitted, written + (per Sonnet) green — needs RUN-CONFIRM then COMMIT:**
- 16 celer unit specs (helpers, composables, stores, atoms, molecules, organisms,
  UsersView unit).
- Infra fixes: `vitest.config.ts` (aliases + VueI18nPlugin), `src/test/mount.ts` (imports).
- 15 units bumped to 1.1.0 (RULES + CHANGELOG, `M`).

**Uncommitted, written, NOT run:**
- `celer/playwright.config.ts`, `celer/e2e/login.spec.ts`, `test:e2e` script + `@playwright/test`.
- `arcus/tests/Feature/Auth/AuthTest.php`, `UserTest.php`.
- `.claude/skills/celer-testing/SKILL.md`, `.cursor/rules/celer-testing.mdc`.

**NOT installed (integration tier blocker):** `@testing-library/vue`, `msw`.

**Not started:** run arcus Pest; install/run Playwright; delete `arcus/tests/*/ExampleTest.php`.

---

## Batch 1 — land the celer unit suite (confirm + commit what's green)

1. `cd celer && npx vitest run` (full unit suite). Use
   `--reporter=json --outputFile=/tmp/x.json` and parse — terminal wrapper mangles pretty output.
2. Fix any red. All 16 specs must pass.
3. Commit specs + infra fixes + the 15 version bumps, grouped by domain batch (helpers,
   composables, stores, atoms, molecules, organisms, UsersView-unit). Stage by path.

**Done when:** `npx vitest run` green; unit specs + bumps committed.

---

## Batch 2 — integration tier (NEW — does not exist yet)

The strategy's missing middle. **Install + build from scratch.**

1. **Install:** `cd celer && npm i -D @testing-library/vue @testing-library/jest-dom msw`.
2. **TL helper:** add `renderWithPlugins` to `src/test/` — TL `render()` with the SAME plugin
   wiring as `mountWithPlugins` (i18n, Pinia, PrimeVue+theme, Toast, Confirm). Keep both
   helpers; `mountWithPlugins` (VTU) stays for unit, `renderWithPlugins` (TL) for integration.
3. **MSW:** `src/test/msw/handlers.ts` (mock the auth/user/role endpoints arcus exposes) +
   `src/test/msw/server.ts`; wire start/reset/close into `src/test/setup.ts`.
4. **Specs** — co-located `<View>.view.integration.spec.ts`, queried via **Testing Library**
   (role/label, not CSS). Cover the frontend contracts the RULES.md promise:
   - `UsersView.view.integration.spec.ts` — list loads; create → row prepended; edit →
     row replaced; delete → row removed (granular updates); API error → toast.
   - `RolesAndPermissions` view — load + permission toggle persistence.
   - Auth login flow (the MLogin* molecules end-to-end) against mocked endpoints.
5. Run green, bump each view/unit to a new minor, commit `test(celer): integration tier (TL + MSW)`.

**Done when:** integration specs green; TL + MSW committed; both helpers documented in the
celer-testing skill.

---

## Batch 3 — celer e2e (Playwright, FULL + robust)

`login.spec.ts` exists but is unrun and only one flow. Strategy = **every shipped flow**.

1. `cd celer && npx playwright install` (browsers). Confirm `make e2e` resolves
   (`test:e2e` script already added).
2. Needs the app running + an arcus backend (or fully network-stubbed). Decide with human:
   stub via Playwright route-interception (preferred, deterministic) vs. real dev servers.
3. **Robust by construction:** `data-testid` selectors only (add testids to components as
   needed — that's a real source change → bump those units), no fixed `sleep`, auth via a
   storageState fixture (`e2e/fixtures/`).
4. Cover: login (all modes), sign-up, forgot-password, users CRUD, roles & permissions.
   Organize by domain: `e2e/auth/`, `e2e/users/`, `e2e/roles/`.
5. Run green (or, if browser/server unavailable in env, the human runs; commit marked
   clearly). Make `celer/e2e/` its own unit (RULES + CHANGELOG + version).

**Done when:** e2e suite green locally; `e2e/` is a versioned unit; committed.

---

## Batch 4 — arcus tests (run, complete, clean)

1. **Unit (Pest):** `tests/Unit/Modules/Auth/Services/` — PermissionService (effective-perms
   merge, memoization), UserService, AuthService with mocked repos; `tests/Unit/Helpers/`
   ApiResponse (envelope shape). Ground in the real classes.
2. **Feature (integration):** finish `AuthTest`/`UserTest`, add `RoleTest`. Full request flow
   + real sqlite, **every endpoint**: auth login/logout/me, user CRUD + archive +
   check-username, role CRUD + permission sync. Assert the layer contracts:
   ApiResponse envelope, mutations-return-the-entity, permission middleware, validation.
   Enable `RefreshDatabase` in `tests/Pest.php` (currently commented).
3. **Run:** `cd arcus && vendor/bin/pest`. Green.
4. **Clean:** delete `tests/Unit/ExampleTest.php` + `tests/Feature/ExampleTest.php`.
5. Bump the mirrored Auth-module units' CHANGELOGs. Commit `test(arcus): Pest unit + feature suite`.

**Done when:** `vendor/bin/pest` green; ExampleTests gone; arcus unit CHANGELOGs bumped.

---

## Batch 5 — skill, Makefile, gate

1. **celer-testing skill** (`.claude/skills/celer-testing/SKILL.md` + `.cursor/rules/celer-testing.mdc`,
   both already drafted) — verify it documents: VTU-vs-TL by tier, `mountWithPlugins` +
   `renderWithPlugins`, MSW, co-location + `.integration.spec.ts` naming, the JSON-reporter
   gotcha. Claude + Cursor parity.
2. **Makefile** — confirm `unit`, `e2e` targets work; add `integration` if you split it from
   unit; add `test` = run all. (`test:e2e` script exists.)
3. **Gate** — the changelog/version check is already in `reviewVueConventions` /
   `reviewArcusCode`. Add: "a changed unit should have a test" expectation, and that the
   generators scaffold a spec stub. Light touch.

**Done when:** skill accurate + parity; Makefile targets all resolve; gate mentions tests.

---

## Out of scope (separate plan)

Architecture, meta, a11y, mutation, type-level → `TESTING-ENHANCEMENTS.md`, a later session
after this base + integration suite is green and committed.

## Sequencing note

Batches are independent enough to commit separately, but do **1 before 2** (integration
builds on confirmed unit infra) and **install TL/MSW (start of 2) before writing any
integration spec**. Batches 3/4/5 can run in any order after 1.
