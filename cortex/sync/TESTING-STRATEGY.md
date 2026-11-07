# Testing Strategy — canonical reference

The conceptual source of truth for *how testing is shaped* in pendulum (and therefore every
project bootstrapped from it). The two execution plans — `TESTING-IMPLEMENTATION.md` (base tiers,
now) and `TESTING-ENHANCEMENTS.md` (guardrail types, later) — implement this shape.

## Reframe: this is a bootstrap, not an app

A normal app optimizes the test pyramid for **run-cost** (few slow e2e, many fast unit).
Pendulum optimizes for two different things:

1. **Propagation value.** A test written once in pendulum runs in *every* project, forever.
   Value ≈ confidence × how cleanly it MERGE-propagates. This makes cheap, shared,
   deterministic tests (architecture, type) disproportionately valuable — they sit *below*
   unit as an even-cheaper, fleet-wide base.
2. **Run-cost is ~zero.** All tests/linters/formatters run in the terminal, no token spend.
   So the usual reason to keep tiers thin (slow to run) does not apply — **build every tier
   full.** The only remaining cost is *authoring* (one-time) and *brittleness* (ongoing).

> **The one guardrail:** "full e2e" must not mean "flaky e2e." Robust by construction —
> `data-testid` selectors (never text/CSS-fragile), no fixed `sleep`, stub the network,
> deterministic fixtures. A flaky test that runs for free still costs trust and human time.

## The shape (wide deterministic base, full middle, robust cap, side-rail)

```
   ┌─ meta-tests — guard the sync machinery (pendulum-only, NOT propagated) ─┐  side-rail

        /\          e2e            FULL coverage of every flow pendulum ships, built robustly
       /  \         a11y           cross-cutting, rides components + e2e
      /    \         integration    REAL tier: view+store+mocked-service (celer) / feature+DB (arcus)
     /------\       unit           broad: helpers, composables, components, stores, services
    /--------\      arch + type    deterministic base — conventions + contracts, ~free, fleet-wide
```

**This diagram shows the conceptual SHAPE, not who-builds-what. Ownership splits across two
execution plans — do not read a tier's position as its timing:**

| Tier | Owned by | Plan |
|---|---|---|
| unit, integration, e2e | base suite (now) | `TESTING-IMPLEMENTATION.md` |
| **architecture, a11y, mutation, type-level** | enhancements (later) | `TESTING-ENHANCEMENTS.md` |
| **meta-tests** | enhancements (later), pendulum-only | `TESTING-ENHANCEMENTS.md` |

> The five enhancement types — **architecture, meta, a11y, mutation, type-level** — are
> specified here only for *shape/placement*. Their implementation spec and the single
> authoritative checklist live in `TESTING-ENHANCEMENTS.md`. arch/type appear at the base of
> the pyramid because that's their conceptual role (cheap, fleet-wide foundation), NOT
> because they ship in the base suite.

## Tier definitions

("Plan" column: **B** = base suite now (`TESTING-IMPLEMENTATION.md`); **E** = enhancements later
(`TESTING-ENHANCEMENTS.md`).)

| Tier | Plan | celer | arcus | Propagates? |
|---|---|---|---|---|
| **arch** | E | dependency-cruiser (module boundaries, barrel-only imports, no cross-component import) | Pest `arch()` — **exhaustive**: every layer rule in `arcus-api-architecture` + layer RULES.md | MERGE |
| **type** | E | Vitest `expectTypeOf` in `*.test-d.ts` | PHPStan (larastan, level 5, the whole of `app/`) — runs in CI, see below | MERGE |
| **unit** | B | helpers, composables, components (**VTU** — props/emits/slots), stores | services/helpers with mocked deps (Pest Unit) | MERGE |
| **integration** | B | view + real store + **MSW**, queried via **Testing Library** (role/label) | **feature tests**: full request flow Route→Controller→…→DB, real sqlite | MERGE |
| **a11y** | E | vitest-axe in component specs | n/a (API) | MERGE |
| **mutation** | E | Stryker (scoped) | Pest `--mutate` (scoped) | MERGE (config, on-demand) |
| **e2e** | B | Playwright — full coverage of shipped flows | (drives the celer UI against a running api) | own unit, MERGE |
| **meta** | E | — | — | **pendulum-only** |

## What CI runs — exactly (`.github/workflows/ci.yml`)

This table is the contract; if the workflow changes, this changes with it. `make pre-commit` is the
local mirror, and its header in the `Makefile` names what it leaves to CI.

| Job | Runs | Where |
|---|---|---|
| `mirror-sync` | `cursor_mirror.py --check`; then, upstream only, `test_sync.py`, `test_backport.py` (after `composer install` in arcus — it reads `vendor/`), `sync.py --audit`, `test_engine.sh`, `test_init.sh` | mirrors everywhere; the rest pendulum only (stripped from a client handoff) |
| `app` | `build-features.mjs --check`, `check:aliases`, `type-check`, `eslint .`, `prettier --check` on code under `src/` (not its Markdown), `test:unit:run`, Playwright e2e | every project |
| `portal` | `docs:extract:check`, `npm test`, markdown lint, `vitepress build` | wherever the docs portal exists |
| `landing` | `npm ci && npm test` (astro check, unit tests, build, `scripts/audit.mjs`) | pendulum only |
| `handbook` | `npm run docs:build` in codelumen | pendulum only |
| `api` | `pint --test`, `phpstan analyse --memory-limit=1G --no-progress` (config `phpstan.neon.dist`: level 5, `app/`), `php artisan test` | every project |
| `audit` | `composer audit --locked` — **informational**: `continue-on-error`, never gates a merge | every project |

"Pendulum only" is a step guard, `hashFiles('.cortex-sync.conf') == ''`: every derived project
tracks that file and pendulum never does. `deploy-api.yml` (LOCAL, pendulum's server) runs its own
Pest and publishes the exact commit that run approved.

PHPStan over `app/` gates the build since 2026-09-17; before that it ran only on the files a branch
touched (`make phpstan-arcus`), and a type error in an untouched file was never seen. Tests stay out
of its paths — see `scripts/phpstan-changed.sh` for why.

**Retired:** "(PHPStan already covers, level in CI)" in the tier table. It was written before any
workflow ran PHPStan, and for as long as it stood the claim was false.

## Querying philosophy — two libraries, split by tier (decided)

celer uses **both** `@vue/test-utils` (VTU) and `@testing-library/vue` (TL). They are not
competitors — TL is a thin wrapper over VTU (same Vitest, same mount underneath); they
encode different *querying philosophies* that map cleanly onto our tiers:

| Use | Library | Why |
|---|---|---|
| **component-contract unit tests** (atoms/molecules: props in, emits out, slots) | **VTU** | here the component's *API is the product*; `wrapper.emitted()` / prop-setting is the right assertion. "Test the implementation" is correct because the implementation IS the public contract for a component-library bootstrap. |
| **view / integration tests** (view + store + MSW) | **Testing Library** | here you test *user behavior* — `getByRole('button',{name:'Save'})`, `getByLabelText`, fill→click→assert-visible. Resilient to refactors, and doubles as **a11y pressure**: role/label queries fail when markup isn't accessible. |
| **e2e** | Playwright (TL-style) | `getByRole`/`getByTestId` — same "query like a user" model as TL, so unit-view-e2e share one mental model. |

Mnemonic: **"testing the component" → VTU; "testing the user" → Testing Library.**

Two honest caveats:
- **PrimeVue renders non-semantic DOM** for some widgets (Select, MultiSelect, DataTable).
  `getByRole` works for most (PrimeVue is reasonably ARIA-compliant) but you WILL fall back
  to `getByTestId` for the complex ones — more than a pure semantic-HTML app would.
- **Existing VTU unit specs stay VTU** — no rework. TL enters at the integration tier (not
  yet built), so the seam is clean.

Helpers (in `src/test/`): keep `mountWithPlugins` (VTU) and add a sibling
`renderWithPlugins` (TL `render()` with the same i18n/Pinia/PrimeVue/Toast/Confirm plugin
wiring). Same plugin setup, two entry points.

## Integration tier — the part that was missing (now explicit)

This is where the highest-value real bugs live, because it exercises the **contracts**:

- **arcus = Pest Feature tests.** Full request flow against a real (sqlite) DB. These verify
  the things the layer RULES.md promise: mutations return the affected entity, ApiResponse
  envelope shape, permission middleware, granular-update semantics, validation. This *is*
  your backend integration tier — name it as such, cover every endpoint.
- **celer = component+store+MSW.** Mount a view with its real Pinia store, mock only the HTTP
  boundary with **MSW** (Mock Service Worker). Verifies the frontend half of the same
  contracts: granular list update on create/edit/delete, error→toast, optimistic flows.
  MSW handlers live in `src/test/msw/` and are shared across integration specs.

## Coverage policy per tier (full, not thin)

- **arch:** exhaustive — encode *every* always/never rule. A failing arch test = a real
  violation; fix the code, never weaken the test.
- **unit:** every helper, composable, store, and component with non-trivial logic.
- **integration:** every arcus endpoint (feature) + every celer view/flow that calls a service.
- **e2e:** every user-facing flow pendulum currently ships — login (all modes), users CRUD,
  roles & permissions, sign-up, forgot-password. Robust selectors only.
- **a11y:** every interactive component + every e2e page.
- **meta/type:** the invariants and contracts that would silently rot.

## Test location — domain-based, by one rule

> **A test lives next to the file it targets — *if* it targets a single file. A test about a
> relationship between many files has no single home; group it by domain within its central
> directory. Never co-locate a cross-cutting test by picking an arbitrary owner.**

"Domain-based" does NOT require physical co-location — it requires **domain-discoverability**.
There are three legitimate ways to be domain-based, by what the test targets:

| Target | Location style | Example |
|---|---|---|
| one file/unit | **co-located** (beside the source) | `MMainDialog.spec.ts` next to `MMainDialog.vue` |
| one domain folder | **co-located in the folder** | `UsersView.view.spec.ts` (unit) + `UsersView.view.integration.spec.ts` (integration) next to the view |
| spans units / cross-cutting | **grouped by domain in a central dir** | `e2e/users/…`, `tests/Feature/Modules/Auth/…`, `tests/Arch/…` |

### What CAN co-locate (do)

- **unit** (component / helper / composable / store) — beside the file.
- **type-level** — `*.test-d.ts` beside the type owner.
- **a11y (component-level)** — an *assertion inside* the component's unit spec, not a file.
- **integration (celer)** — beside the view it targets. **Decision: name them
  `<View>.integration.spec.ts`** to sit alongside the unit `<View>.spec.ts` in the same
  folder (flat, no `__tests__/` subfolder). Both are domain-co-located; the suffix
  distinguishes tier.

### What CANNOT co-locate (and why — not preference, structure)

- **arch** — targets a *relationship across all files* ("no service imports Eloquent");
  belongs to no single file. → `arcus/tests/Arch/`, celer `.dependency-cruiser.cjs`.
- **e2e** — a user journey across many domains; no single owner. → `celer/e2e/` (grouped
  by domain: `e2e/auth/`, `e2e/users/`).
- **meta** — tests the sync machinery, not app code at all. → `cortex/tests/`.
- **arcus integration (feature)** — a request flow across 6 layers; no single owner. Plus a
  hard framework constraint (below). → `arcus/tests/Feature/Modules/<Module>/`.

### arcus cannot co-locate at all (framework constraint, verified)

`phpunit.xml` discovers tests only under `tests/Unit` + `tests/Feature`; `composer.json`
`autoload-dev` maps `Tests\` → `tests/` only; `tests/Pest.php` binds `TestCase`/DB traits to
those dirs. A spec dropped in `app/Modules/Auth/Services/` would be undiscovered,
un-autoloaded, and trait-less. So arcus stays domain-based **by mirrored path** instead of
co-location: `tests/Feature/Modules/Auth/…` mirrors `app/Modules/Auth/…`. The
"tests are part of the unit's version" rule still holds — bump the mirrored unit's CHANGELOG.

## File organization

### celer — co-located (unit + integration + type beside the unit; e2e separate)

```
celer/
  src/
    helpers/
      bodyScrollLock.helper.ts
      bodyScrollLock.helper.spec.ts            # unit
    composables/
      useGlobalAbortController.ts
      useGlobalAbortController.spec.ts          # unit
    components/molecules/MMainDialog/
      MMainDialog.vue
      MMainDialog.spec.ts                       # unit — VTU (props/emits/slots)
      RULES.md
      CHANGELOG.md
    modules/User/
      store.ts
      store.spec.ts                             # unit (store)
      interfaces.ts
      interfaces.test-d.ts                      # type-level
      views/Users/
        UsersView.view.vue
        UsersView.view.spec.ts                  # unit — VTU (view isolated, mocked deps)
        UsersView.view.integration.spec.ts      # INTEGRATION — Testing Library (view + store + MSW)
        RULES.md
        CHANGELOG.md
    test/
      setup.ts                                  # global vitest setup
      mount.ts                                  # mountWithPlugins (VTU) + renderWithPlugins (Testing Library)
      msw/
        handlers.ts                             # shared HTTP mocks for integration
        server.ts
  e2e/                                          # Playwright — its own versioned unit
    RULES.md
    CHANGELOG.md
    fixtures/
      auth.ts                                   # storageState / login helper
    auth/
      login.e2e.ts
      sign-up.e2e.ts
      forgot-password.e2e.ts
    users/
      users-crud.e2e.ts
    roles/
      roles-permissions.e2e.ts
  vitest.config.ts                              # (config-unit)
  playwright.config.ts                          # (config-unit)
  .dependency-cruiser.cjs                       # arch (config-unit)
  stryker.conf.json                             # mutation (config-unit, on-demand)
```

### arcus — Pest `tests/` tree (NOT co-located — PHP/Pest idiom)

```
arcus/
  app/Modules/Auth/...                          # source (RULES.md + CHANGELOG.md per unit)
  tests/
    Pest.php
    TestCase.php
    Arch/
      ArchTest.php                              # architecture — exhaustive, all layer rules
    Unit/
      Modules/Auth/Services/
        PermissionServiceTest.php               # unit — pure logic, mocked repos
      Helpers/
        ApiResponseTest.php
    Feature/
      Modules/Auth/
        AuthLoginTest.php                       # INTEGRATION — full flow + DB
        UserEndpointsTest.php                   # every user endpoint
        RoleEndpointsTest.php                   # every role/permission endpoint
```

> **arcus co-location exception (decided):** Pest/Laravel expects a `tests/` tree, so arcus
> tests are NOT co-located. The "tests are part of their unit's version" rule still holds —
> when a test for `Repositories/` is added, bump the `Repositories/` unit's CHANGELOG even
> though the test file lives under `tests/`. The mapping is by path convention
> (`tests/Feature/Modules/Auth/UserEndpointsTest.php` ↔ the Auth module units).

### cortex — meta-tests (pendulum-only, side-rail)

```
cortex/
  tests/
    test_metadata_invariant.py                  # version == changelog top, 1:1 rule, frontmatter
    test_transform_roundtrip.py                 # sync.py token protection
    test_alias_lockstep.py                      # vite ↔ tsconfig aliases match
```

## How this feeds Plan B (sync)

- arch / type / unit / integration / a11y specs → **MERGE** (ride their unit or config-unit).
- `celer/e2e/` → **its own MERGE unit** (RULES+CHANGELOG+version).
- `cortex/tests/` → **pendulum-only**, never propagated.
- Post-merge gate (`DESIGN.md` lifecycle step 5) runs the full local suite — free, terminal.
