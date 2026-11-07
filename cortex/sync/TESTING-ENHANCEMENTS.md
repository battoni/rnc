# Testing Enhancements — Plan (point Sonnet here)

A SEPARATE session, run AFTER the base suite is green (see `TESTING-IMPLEMENTATION.md`, the
authoritative base plan; `TESTING-HANDOFF.md` is superseded).
This adds **guardrail test types** that turn pendulum's conventions and machinery into
executable guarantees. This list is **open** — more types will be appended; keep the
structure extensible.

**This document is the single authoritative owner of these five test types** (others may
reference them for shape, but the spec + checklist live here):

1. Architecture tests — §1
2. Meta-tests on the convention/sync system — §2
3. Accessibility (a11y) — axe-core — §3
4. Mutation testing — §4
5. Type-level tests — §5

## Working agreement (same as the base buildout)

- **Human runs the tests** and pastes results back. Don't loop/probe on a flaky tool
  channel — write, ask the human to run, fix from feedback.
- **Never commit unrun tests.** A test counts as done only after a confirmed green run.
- Ground every test in REAL code — read the file first; never assume an API exists.
- Per-unit changelog/version bump where a test attaches to a unit (see `SCHEMA.md`).
- Prereq: base Vitest + Playwright (celer) and Pest (arcus) infra already committed.

## Sync-model classification (decide MODE per type — feeds Plan B manifest)

| Type | Lives where | MODE | Notes |
|---|---|---|---|
| Architecture (arch) | `arcus/tests/Arch/`, celer dep-cruiser config | MERGE | pendulum-authored, shared across fleet |
| Meta-tests (sync system) | `cortex/tests/` (pendulum root) | **pendulum-only, not propagated** | guards pendulum's own machinery; projects don't run these |
| A11y | co-located in component/e2e specs | MERGE | rides the unit / e2e unit |
| Mutation | config at celer + arcus root | MERGE (config) | runs in CI/on-demand, not per-commit |
| Type-level | co-located `*.test-d.ts` beside the type owner | MERGE | rides the unit |

> Meta-tests are the one exception to "everything propagates" — they test pendulum's
> RULES/CHANGELOG/transform system, which only exists at the pendulum root.

---

## 1. Architecture tests  (highest leverage — do first; make EXHAUSTIVE)

Turn the written "always/never" conventions into deterministic, instant guarantees.
Replaces judgment-based AI-gate enforcement with executable enforcement for the
mechanical rules. **Goal: complete coverage — encode EVERY always/never rule in
`arcus-api-architecture.md`, the layer RULES.md files, and the celer convention rules,
not just the samples below.** A failing arch test = a real violation; fix the code, never
weaken the test. (Run-cost is free, so there is no reason to keep this tier thin.)

### arcus — Pest `arch()`  (`arcus/tests/Arch/ArchTest.php`)
Encode the rules already written in `arcus-api-architecture.md` + the layer RULES.md:
- Controllers are thin: `App\Modules\*\Controllers` → `not->toUse('Illuminate\Support\Facades\DB')`
  and not the Eloquent namespace.
- Services never query Eloquent directly: `App\Modules\*\Services` → `not->toUse('Illuminate\Database\Eloquent')`.
- Repositories are the only Eloquent layer (assert models used only under `Repositories`).
- `env()` only in `config/` (Pest can assert `App\` → `not->toUse('env')`... verify the
  helper-detection approach with `search-docs`).
- DTOs are Spatie `Data` subclasses; Requests extend `FormRequest`; Resources extend
  `JsonResource`.
- No `dd`/`dump`/`ray` left in `App\`.
- Run: `cd arcus && vendor/bin/pest --group=arch` (or just the Arch dir).

### celer — dependency-cruiser  (`celer/.dependency-cruiser.cjs`)
ESLint covers most line rules; dep-cruiser covers MODULE-BOUNDARY rules it can't:
- No import from a module sub-path (only the `@XModule` barrel) — mirrors the
  `no-restricted-imports` rule but graph-level.
- No cross-module imports except via barrels.
- No component importing another component directly (auto-import contract).
- Add script `test:arch` → `depcruise src --config`. Wire into `make`.

**Deliverable:** arch suites green on current code (they should pass — code already follows
the rules; if one fails it found a real violation — report it, don't weaken the test).

---

## 2. Meta-tests on the convention/sync system  (pendulum-only)

Promote the throwaway verification scripts into committed tests at `cortex/tests/`.
Pick the runner that's least friction (a standalone `pytest` or even a `*.spec.ts` run by
celer's vitest pointed at repo root, OR a small `make check-meta`). Assert:

- **1:1 rule:** every `RULES.md` (under celer/src, arcus/app, arcus/database, *.config-rules)
  has a sibling `CHANGELOG.md`.
- **Version invariant:** each `RULES.md` frontmatter `version` == its `CHANGELOG.md` top
  entry. (This is the `checked=N mismatches=0` script — make it a test.)
- **Frontmatter completeness:** every RULES.md has `version`/`origin`/`based-on`.
- **Transform round-trip:** `sync.py` specialize() protects `celer-`/`arcus-` filename
  tokens — feed known input, assert tokens survive, project words map.
- **Alias lockstep:** every alias in `celer/vite.config.ts` exists in `tsconfig.app.json`
  paths and vice versa.
- **SemVer well-formedness:** every changelog top entry matches `X.Y.Z — YYYY-MM-DD`.

**Deliverable:** `make check-meta` (or equivalent) green; wire it so it's runnable in CI.
This is the load-bearing guard for the whole decade-scale sync system.

---

## 3. Accessibility (a11y) — axe-core

Bootstrap-level a11y propagates to every project for free.
- celer unit: add `vitest-axe` — assert `expect(await axe(container)).toHaveNoViolations()`
  in key component specs (forms, dialogs, navbar).
- celer e2e: add `@axe-core/playwright` — scan rendered pages (login, a CRUD view) for
  violations.
- Start with WCAG A/AA rule set; treat existing violations as findings to report, then fix
  at the bootstrap (cheaper here than in N projects).

**Deliverable:** a11y assertions in a few reference component specs + one e2e page scan,
green (or violations reported for triage).

---

## 4. Mutation testing  (quality signal — slower, on-demand)

Tests the tests: mutate source, confirm a test fails. Run on-demand/CI, not per-commit.
- arcus: Pest mutation — `vendor/bin/pest --mutate` (Pest 4 built-in). Scope to one module
  first (Auth) to bound runtime; set a `--min` mutation-score threshold.
- celer: **Stryker** (`@stryker-mutator/core` + vitest runner). Scope to helpers/composables
  first.
- Add `test:mutate` scripts; document expected runtime (slow). Do NOT gate commits on it —
  it's a periodic quality audit.

**Deliverable:** mutation runners configured + a baseline score per side, scoped to a
starter module. Tune thresholds later.

---

## 5. Type-level tests  (cheap, given heavy TS contracts)

Assert the TypeScript contracts themselves, beyond runtime behavior.
- Use Vitest's `expectTypeOf` / `assertType` in co-located `*.test-d.ts` files.
- Target: module interface/payload types, store getters, composable return shapes,
  service signatures — places where a type regression would silently break consumers.
- Ensure `vitest` typecheck mode is on (`test.typecheck.enabled` or `vitest --typecheck`).

**Deliverable:** `test-d` specs for a few representative typed contracts, green under
`vitest --typecheck`.

---

## More types (to be appended)

The human has additional test types to discuss — leave room. Each new type should record:
its home, its sync MODE, its runner/script, whether it gates commits or runs on-demand,
and a bounded first deliverable. Keep the same "real code, run-green, per-unit version"
discipline.

## After this: feeds Plan B

Every MERGE-classified test type becomes a manifest entry; meta-tests stay pendulum-only.
The post-merge gate (`DESIGN.md` lifecycle step 5) can grow to run arch + a11y alongside
unit/e2e. Update `DESIGN.md`'s manifest section once MODEs are finalized here.
