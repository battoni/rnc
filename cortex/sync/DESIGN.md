# Pendulum → Projects Sync — Design

Status: **proposal for review** (nothing built yet beyond the overwrite tool in this folder).

## Goal

Pendulum is the source of truth. Improvements made in pendulum — AI rules, shared
components (+ their `RULES.md`), API features/scaffolding, project config — must flow
to every downstream project that was bootstrapped from it, **without silently
overwriting changes those projects made locally.**

> **Safety model (post-impressão).** Unit metadata alone could not see file-level extensions
> to *shared* junction files (routes, nav, locales, seeders, permission registries, config), so a
> T0 "take theirs" silently reverted them. The fix is two-layered: a **structural** half — the
> core/local split, where every junction file has a `LOCAL` `*.local.*` half the sync can never
> touch (so extensions cannot be reverted by construction) — and an **engine** half: the loss
> report + pre-sync rollback tag, junction-aware `--resolve-t0`, the `--advance` test gate, and the
> `--brand-audit`. See `RUNBOOK.md` → "Core/local extension points" and "Safety nets".

## Hard constraints (from how things actually are)

1. **Separate repos, no shared git history.** Projects were copied from pendulum, not
   git-forked. There is no common commit, so a plain cross-repo `git merge` has no
   ancestor to reason about.
2. **Names are embedded.** Pendulum says `celer`/`arcus`/`pendulum`; each project uses
   its own (`app.impressao`/`api.impressao`/`impressao`). Propagation always needs the
   name transform we already built and tested (`sync.py`, token-protected).
3. **Two kinds of file.** Some are never edited downstream (rules, commands, hooks) —
   safe to overwrite. Some *are* edited downstream (components, views, API modules) —
   overwriting them is data loss.
4. **Right now, divergence is brand-only.** No major logic has forked yet. This is the
   cheap moment to establish the baseline that makes future merges safe.

## The core idea: a synthetic "upstream" branch per project

Git's 3-way merge needs three inputs: theirs (pendulum), ours (project), and the
**common ancestor**. We don't have one across repos — so we **manufacture** one,
inside each project repo, as a dedicated branch.

```
project repo
├── main                         ← your real work (brand changes, future logic)
└── pendulum-upstream            ← "pendulum, name-mapped to THIS project"
                                   nothing but transform output ever lands here
```

`pendulum-upstream` is a vendor branch. Its commits are *only ever* produced by running
the transform on pendulum at a given version. Because each update is a commit on that
branch, **the previous commit is the ancestor** git uses when merging — which is exactly
the missing piece.

### Lifecycle

**T0 — baseline (do once per project, now, while divergence is brand-only):**
1. Create empty branch `pendulum-upstream` in the project.
2. Run the transform: pendulum@V0 → name-mapped files → commit on `pendulum-upstream`.
   Tag the pendulum SHA in `.cortex-version`.
3. `git merge pendulum-upstream` into `main` (the first time needs
   `--allow-unrelated-histories`). Today this is **near-empty / conflict-free** because
   `main` already ≈ transformed pendulum. That merge commit ties the two histories
   together; from now on git has a real merge base.

**Tn — every later pendulum update:**
0. **Source-green precondition (hard gate).** `sync.py` confirms pendulum's own suite is
   green at the SHA being synced *before* generating `pendulum-upstream`. "Source of truth"
   must mean "*verified* source of truth" — syncing from a red pendulum would propagate
   breakage fleet-wide. Refuses on red; overridable only with an explicit `--force`.
1. `git checkout pendulum-upstream`
2. Re-run the transform: pendulum@Vn → overwrite branch contents → commit. (The branch
   moves forward by *exactly pendulum's delta*, already name-mapped.)
3. `git checkout main && git merge pendulum-upstream`.
   - Files the project never touched → fast-forwarded to pendulum's new version
     automatically.
   - Files both sides changed → **normal git conflict**, surfaced for a human, never
     silently lost. A changed component and its changed `RULES.md` conflict together
     (same folder, ordinary files).
4. Resolve (if any), commit.
5. **Run the test suite** (`make unit` + `make e2e`). This is the *semantic* safety net on
   top of git's *textual* one: a merge can apply cleanly yet still break behavior (pendulum
   changed a unit's logic; the project's surrounding code relied on the old behavior; no
   line conflict, but it's broken). **Green = safe. Red with no conflict = the merge
   introduced a behavioral break — investigate before continuing.** Only then does
   `.cortex-version` advance.

This is the standard **vendor-branch** pattern. The transform's only job is "generate
the upstream branch"; **git does the textual merge correctly, and the test suite verifies
the result behaviorally.**

## Why this over the alternatives

| Approach | Verdict |
|---|---|
| Overwrite-sync everything | Fine for no-edit files; **data loss** for edited code. |
| npm/composer package the shared code | A bootstrap is *meant to be edited* — you can't package a fork. Good only for a truly-invariant subset (some atoms, helpers, API scaffolding) — a later optimization, not the general answer. |
| shadcn-style per-unit registry + versions | Reimplements 3-way merge by hand with per-unit ancestors. Git already does this. |
| Teach `sync.py` to 3-way merge itself | Reinventing `git merge`. Don't. |

## Per-file behaviour: one manifest, a SYNC MODE column

Extend `manifest.tsv` so each path (or glob) declares how it propagates:

| MODE | Meaning | Mechanism |
|---|---|---|
| `OVERWRITE` | never edited downstream (rules, commands, hooks) | transform → write directly to `main` (today's tool) |
| `MERGE` | editable (components+RULES.md, views, API modules, config) | transform → `pendulum-upstream` → `git merge` |
| `LOCAL` | project owns it (CLAUDE.md, brand/theme, project-context, roster, settings.json) | never touched |
| `SKIP` | never synced (settings.local, build output, sync metadata) | ignored |

One file drives both mechanisms. You retune modes as you learn which files projects
actually edit. Start conservative: anything under `src/` that a project might touch =
`MERGE`; the AI layer = `OVERWRITE`; identity/brand = `LOCAL`.

**Test files (added by the testing buildout) classify cleanly into existing modes:**

| Test artifact | MODE | Why |
|---|---|---|
| Co-located unit specs (`<Unit>/<Unit>.spec.ts`) | `MERGE` | ride with their unit; a changed pendulum test conflicts only where the project changed the same test |
| Unit test infra (`vitest.config.ts`, `playwright.config.ts`, `src/test/`) | `MERGE` (part of the **config-unit**) | projects may tweak; merge surfaces overlap |
| Cross-unit e2e (`celer/e2e/`) | **its own MERGE unit** (RULES+CHANGELOG+version), see "Test layer" below | flows span multiple units; belong to no single unit's version |
| arcus Pest (`arcus/tests/`) | `MERGE` | same logic as celer specs |
| CI workflow (`.github/workflows/*.yml`) | `MERGE` | pendulum ships green-gating CI; every project inherits it. Pendulum's CI *additionally* runs the pendulum-only meta-tests |

No new *mode* is needed — the four still suffice.

> ✅ **Test/config tokens — verified non-issue (2026-06-01 review).** As actually built,
> the test/config layer uses generic `http://localhost(:5173)` everywhere: MSW handlers →
> `http://localhost`, `VITE_API_URL` → `http://localhost`, Playwright `baseURL` →
> `http://localhost:5173` (the Vite default port, identical across projects, already
> overridable via `E2E_BASE_URL`). The only name-bearing tokens are module aliases and
> `celer`/`arcus` dir words — **already handled** by the existing `specialize()` rewrite.
> So no new token class is needed; the worry that prompted this note (`api.zion`,
> per-project host:port) does not apply to the bootstrap. The single residual lever is a
> project that changes its dev port — handled by `E2E_BASE_URL` or a `LOCAL` line, not the
> transform. (arcus tests use the Laravel `App\` namespace — no transform needed.)

## What "a component changes" looks like end-to-end

1. You improve `celer/src/components/molecules/MUserCard/` in pendulum (the `.vue` and
   its `RULES.md`), commit.
2. In project X: `sync` regenerates `pendulum-upstream` (MUserCard now name-mapped to
   `app.x`), commits.
3. `git merge pendulum-upstream`:
   - Project never touched MUserCard → it just updates. RULES.md **and the co-located
     `MUserCard.spec.ts`** ride along.
   - Project had customized MUserCard → git conflict on exactly the overlapping lines;
     you resolve in a normal diff view. Nothing is silently lost either way.
4. Run `make unit` → the merged-in `MUserCard.spec.ts` verifies the result behaves as
   pendulum intends. If the project *intentionally* diverged MUserCard, pendulum's test may
   now fail against it — **that is correct signal, not a bug to revert** (see Test layer).

## Naming / transform

Unchanged from the built tool: rewrite `celer`/`arcus`/`pendulum` words, **protect
`celer-`/`arcus-` filename tokens**, driven by each project's `.cortex-sync.conf`
(`PROJECT` / `APP` / `API`). The transform is the same for OVERWRITE and MERGE — only
*where the output lands* differs (main vs. the upstream branch).

## Per-project files

- `.cortex-sync.conf` — name mapping (exists for impressao already).
- `.cortex-version` — last pendulum SHA synced (forward-only reviews).
- branch `pendulum-upstream` — the synthetic ancestor (the new piece).

## Relationship to `/setup-project` (decided)

Each project's git history already contains the ancestor we need. Example — zion:

```
88038fa  Initial commit using pendulum project as bootstrap   ← pendulum content, celer/arcus names
...
f020137  refactor: Rename project from Pendulum to Zion        ← /setup-project ran here (the transform)
```

So the recorded workflow is: clone pendulum → delete `.git` → delete unused module
folders → `git init` → connect new remote → run `/setup-project`. That means:

- The **"Initial commit … bootstrap"** is a real, verified snapshot of pendulum at fork
  time (already minus unused modules). It is the truest possible **ancestor reference** —
  we never have to reconstruct it.
- **`/setup-project` IS the name transform, run once** (celer→app.X, arcus→api.X, plus
  deletion of unused-module rules). Our `sync.py` is the same transform, run every later
  time.

**Decision — two commands, one shared transform engine:**

- `/setup-project` stays a **separate, first-run** command (it runs before any
  `pendulum-upstream` branch exists, on a fresh repo, and additionally *deletes* unused
  modules). It should be **adapted to call the shared transform engine** rather than
  re-implement renaming by hand.
- `sync.py` is the **ongoing** command. Same engine, different surrounding workflow
  (writes to the vendor branch + merges, never deletes modules).

**Decision — seed the ancestor from the bootstrap commit, transformed:**

The `pendulum-upstream` branch must hold content in the **project's** names (app.X), so
its base lines up with `main` and the merge only conflicts on real local edits. The
bootstrap commit is celer/arcus-named, so we do **not** reset the branch to it raw —
we seed the branch with **transform(bootstrap tree → project names)**, which equals the
state `/setup-project` produced. The bootstrap commit remains the *verification source*
(we can prove the seed matches the recorded fork), but the branch content is the
transformed form. From there the branch advances forward with each sync.

> ⚠️ Naming subtlety: never seed `pendulum-upstream` from the raw celer-named bootstrap
> commit — every file would differ from `main` by naming alone and the merge would
> conflict everywhere. Always seed from the *transformed* tree.

## Per-unit metadata layer (RULES + CHANGELOG + version) — decided

**Why this exists:** the bootstrap workflow (`clone → delete .git → git init`) destroys
git history. A new project has no record of where each component came from or how it
evolved. A co-located `CHANGELOG.md` is the *only* history that survives the copy — it is
the portable memory git cannot provide across the repo boundary.

**The triad, co-located per important unit** (component, view, module — frontend AND API,
at least per API module):

```
MMainDialog/
  RULES.md        ← current contract (what an AI reads to USE the unit)
  CHANGELOG.md    ← history of deltas (what propagation reads to TRIAGE)
  MMainDialog.vue
```

- **1:1 rule** — if a unit has a `RULES.md`, it has a `CHANGELOG.md`. Born together,
  travel together. (~59 units in celer today.)
- **Version = SemVer** (`major.minor.patch`). Major = breaking contract change (propagation
  must look carefully); minor = additive; patch = tweak. The bump *is* the triage signal.
- **CHANGELOG is canonical for the version.** You hand-write one changelog entry with its
  version; `RULES.md` frontmatter only *mirrors* the top entry. Single edit, single truth.
- **RULES.md gains frontmatter** (one-time format change to existing files):

  ```
  ---
  version: 1.4.0          # MUST equal CHANGELOG.md top entry
  origin: pendulum        # where this unit came from
  based-on: 1.4.0         # pendulum version this was last synced from
  ---
  ```

  `version` vs `based-on` is the propagation gauge: if a project's unit is `version 1.5.0`
  but `based-on 1.4.0`, the project has local edits on top of pendulum 1.4.0 — merge will
  need care. If they're equal, it's pristine pendulum — safe fast-forward.

**Roles stay separate (no overlap):** version/provenance = the *triage dashboard* (which
units need attention); **git 3-way merge still executes** the actual change. Metadata
guides, git applies.

### Enforcement — fold into existing gates (decided)

A convention nobody enforces rots. Same pattern already used for vue/arcus conventions —
three touchpoints:

1. **Generation:** `generate-component-rules` also scaffolds `CHANGELOG.md` (`0.1.0`
   entry) and stamps `RULES.md` frontmatter. Rule + changelog are created together.
2. **During work:** `/commit` and the create-pr checklist gain a step — "if you changed
   code in a unit, add a CHANGELOG entry and bump its SemVer."
3. **The gate (keystone):** **folded into** `reviewVueConventions` + `reviewArcusCode`
   (not a separate command). For every unit whose code changed on the branch, assert:
   (a) a new CHANGELOG entry exists, and (b) `RULES.md version:` equals the CHANGELOG top
   entry. Violations flagged like any other convention. This converts "remember to update
   the changelog" into "the review won't pass until you did."

### Deliberately NOT built yet

- **No separate "pendulum vs project additions" ledger.** `.cortex-version` + merge
  commits + per-unit changelogs already reconstruct it. Don't invent it until the per-unit
  system exists and a real gap appears.
- **Start 1:1, relax only if it hurts.** If specific leaf components churn with trivial
  entries, allow coarser grain *there* — but don't pre-optimize.

### How this powers propagation triage

When you change something in pendulum and want to propagate: the tool compares each
unit's pendulum `version` against the project's `based-on`. Output is a triage table —
*unchanged* (skip), *clean update* (git fast-forwards), *locally diverged* (git merge,
review the conflict). The SemVer major/minor/patch tells you how hard to look. You read
*what* changed straight from the unit's CHANGELOG, no diffing required. git does the merge.

## Resolved decisions

1. **MERGE granularity — ALL of `src/` plus config.** Everything mergeable gets the
   vendor-branch treatment. Config files (vite/tsconfig/eslint) and other important
   non-component units **also get RULES + CHANGELOG + version** — see "Units beyond
   components" below for how that works for things that aren't a folder.
2. **API scope — included now.** `arcus` (api.*) gets the same vendor-branch + per-unit
   metadata treatment as the frontend, at least per API module.
3. **Rollout — prove on zion first.** zion is the proving ground (clean bootstrap commit,
   real divergence, own remote). Establish T0 baseline + one full Tn cycle on zion, then
   fan out to the rest.
4. **Direction — pendulum → projects only.** A project improvement worth promoting back is
   a manual cherry-pick to pendulum, never automatic.

### Units beyond components (config, single-file scaffolding)

Decision #1 ("RULES + CHANGELOG for that kind of stuff as well") raises one structural
issue: the triad assumes a **folder** (`MMainDialog/` holds its own RULES+CHANGELOG).
Config files are loose files at the project root (`vite.config.ts`, `tsconfig.json`,
`eslint.config.ts`) — they have nowhere to put a sibling `RULES.md` without clutter.

Resolution — **group loose config as one logical unit** under a single home, e.g.
`config/` docs in cortex or a `build-config` unit:

```
celer/.config-rules/
  RULES.md        ← contract for the build/config layer (what each config owns, what not to touch)
  CHANGELOG.md    ← versioned history of config changes
```

The actual config files stay where tools expect them (root); their RULES+CHANGELOG live
in one co-located meta-folder that the manifest maps to those files. This keeps the
"RULE + CHANGELOG for everything important" principle without scattering meta-files or
fighting toolchain path expectations. Same pattern for any group of loose files that form
one logical concern.

> Rule of thumb: **one RULES+CHANGELOG per logical unit, not per file.** A folder-unit
> (component, view, module) maps to itself; a file-group unit (build config, root scaffold)
> maps to a small meta-folder. The manifest records which files each unit governs.

### Test layer (decided — added after the testing buildout)

Tests are propagated and versioned, with three shapes:

1. **Unit specs are part of their unit.** A co-located `<Unit>.spec.ts` is just another file
   in the unit folder; changing it bumps the unit's CHANGELOG/version like any other edit.
   No separate version. It merges with its unit.
2. **Test infra rides the config-unit.** `vitest.config.ts`, `playwright.config.ts`,
   `src/test/` belong to the `.config-rules` unit's version.
3. **E2e is its own versioned unit** (`celer/e2e/` with its own RULES+CHANGELOG+version).
   *Rationale:* e2e flows are cross-unit (login spans Auth + router + store), so they belong
   to no single unit's version, but they *are* bootstrap value worth propagating — hence a
   unit of their own rather than `LOCAL`. **(Resolved 2026-06-01: MERGE, per Phase B locked
   decision 5 — the lever is closed.)**

**Why tests matter to the sync system specifically:** they complete the safety promise.
git's 3-way merge guarantees no *textual* loss; the test suite, run post-merge (lifecycle
step 5), guarantees no *behavioral* break. Together: clean merge + green tests = safe;
clean merge + red tests = a real behavioral conflict to investigate.

**Intentional-divergence semantic (document in RUNBOOK):** a project that deliberately
forked a unit's behavior will see pendulum's merged-in test fail against it. That is the
**correct** signal — the test layer is a *behavioral* conflict-surfacer complementing git's
textual one. The project then either adapts the test (its own version) or accepts the
divergence. **Never "fix" an intentional fork back to pendulum just because a test went red.**

`/setup-project` also prunes test artifacts for deleted modules: co-located specs vanish
with the folder (co-location pays off), but it must also prune everything that *references*
the deleted module from outside its folder:
- `vitest.config.ts` `moduleSubAliases` + any `playwright.config.ts` project list,
- the module's **MSW handlers** in `src/test/msw/handlers.ts`,
- the module's **arcus feature dir** `arcus/tests/Feature/Modules/<X>/`,
- the module's **e2e specs** `celer/e2e/<x>/`.
Same principle as pruning rule globs today, just a wider surface.

**Triage refinement — test-only bumps are low-risk.** A unit whose version bumped because
only its *spec* changed (not its source) is safer to merge than a logic change. The
version-triage table may flag "test-only" so a reviewer looks less hard — optional polish on
the triage output, not a correctness requirement.

## Phase B — locked decisions (2026-06-01 readiness review)

Three Phase-B mechanics, settled before B1:

1. **Manifest schema — glob-based, first-match-wins, no implicit default.** The manifest
   stays `MODE <TAB> PATH-glob`. Classify by broad location glob: `celer/**` + `arcus/**`
   (mergeable code + co-located tests) → `MERGE`; `.claude/**`, `.cursor/**`, `cortex/**`
   (the AI layer) → `OVERWRITE`; identity/brand/roster → `LOCAL`; build artifacts + sync
   metadata → `SKIP`. Specific `LOCAL`/`SKIP`/`OVERWRITE` exceptions are listed **above** the
   two broad catch-all globs, so first-match-wins resolves every file to an explicit mode —
   the old implicit "TEMPLATED default" is removed (every file must classify).

2. **Automation boundary — `sync.py` stops at the upstream commit.** It automates the
   deterministic half: checkout `pendulum-upstream`, regenerate via the transform, commit.
   Then it STOPS. The human runs `git merge pendulum-upstream`, resolves conflicts, and runs
   the suite. Conflict resolution is irreducibly human judgment; the commit is safe to
   automate, the merge is not. (The RUNBOOK documents the merge + resolve + test steps.)

3. **Source-green check — query CI, don't run locally.** The Tn step-0 gate reads the CI run
   conclusion for the synced SHA via `gh api` (the workflow shipped in B4) rather than
   running `make test-all` locally. Cheap, authoritative, and reuses the green-gating CI.
   Refuse to generate `pendulum-upstream` if the SHA's CI run is failing or absent; `--force`
   overrides.

4. **Triage output — full dashboard from v1.** The version-triage table renders, per unit:
   the `version` vs `based-on` state (unchanged / clean-update / locally-diverged), the SemVer
   severity of the delta (major/minor/patch — how hard to look), **and** an inline excerpt of
   the unit's CHANGELOG top entries since `based-on` (what changed, no diffing). This is the
   richest of the three options and the **heaviest novel logic in B2** — build it behind
   `--dry-run`, isolate it, and unit-test it hardest. The "test-only bump = low-risk" flag is
   part of this dashboard.

5. **e2e is MERGE (the open lever is closed).** `celer/e2e/` propagates as its own versioned
   unit (RULES + CHANGELOG + version), not `LOCAL`. Projects inherit pendulum's flows; a
   forked flow surfaces as a merge conflict and/or a failing inherited test — the intended
   behavioral-divergence signal. Heavy forkers may still override locally per-project.

6. **A.6 gate is hardened before B1.** The changelog/version assertion (every changed unit has
   a new CHANGELOG entry and `RULES.md version` == CHANGELOG top entry) is folded into
   `reviewVueConventions` + `reviewArcusCode` *before* the engine ships, so the triage in
   decision 4 reads enforced, trustworthy versions rather than drifted ones. See build-order
   step B0.

**Two now-stale facts corrected from this review:**
- The MERGE engine is **greenfield.** Today `sync.py` scans only `.claude`/`.cursor`/`cortex`
  (it is purely the OVERWRITE tool). B2 must extend the scan to `celer/` + `arcus/` and add
  the vendor-branch orchestration — the larger half of the engine, not an "extension."
- The test/config token transform is a **non-issue** (see the ✅ note above): the layer uses
  generic `localhost`, not project tokens, so B2 drops the "new token class" work.

## Build order (once design approved)

Sequenced so each step is verifiable before the next depends on it.

**Phase A — metadata foundation (in pendulum, no propagation yet)**

> Audited state (2026-05-30): celer/src has **59 RULES.md, 0 CHANGELOG.md**. arcus has
> **0 RULES.md, 0 CHANGELOG.md** (only global guidance: `arcus-api-architecture` rule +
> `arcus-laravel-best-practices` skill). So Phase A is THREE different-sized passes, not a
> uniform "backfill."

1. **Schema.** Define RULES.md frontmatter (`version` / `origin` / `based-on`) and the
   `CHANGELOG.md` SemVer format. Document in `cortex/sync/`.

2. **A-celer-changelog (mechanical, large).** For each of the 59 existing celer RULES.md:
   add frontmatter + create a sibling `CHANGELOG.md` seeded with a single entry:
   `1.0.0 — Initial documented baseline (2026-05-30)`. No fabricated history; real entries
   accrue going forward.

3. **A-arcus-rules (authoring, high-judgment — NEW work, the doc layer doesn't exist).**
   arcus currently has only the `Auth` module. Author RULES.md (+ `1.0.0` CHANGELOG) for:
   - **Module level:** `app/Modules/Auth/RULES.md` — domain, what belongs / doesn't,
     the public surface, request-flow contract.
   - **Per architectural layer in the module** (the request flow has real intentional
     decisions worth capturing): `Controllers/`, `DTOs/`, `Repositories/`, `Services/`,
     `Requests/`, `Resources/` — semantic repository naming, DTO boundaries, thin
     controllers, ApiResponse contracts, granular-update-returns-entity.
   - **Shared app layer:** `app/Helpers/` (ApiResponse), `app/Models/` (User), and a
     `database/` unit (factories + seeders conventions).
   - Needs an **arcus-flavored generator** (see step 4) that reads
     `arcus-api-architecture` + the laravel-best-practices skill and captures backend
     intentional decisions, NOT Vue prop/emit contracts.

4. **Generators.** `generate-component-rules` (celer/Vue) scaffolds the triad for new
   units. Add a sibling **`generate-api-rules`** (arcus/Laravel) for backend units. Both
   stamp frontmatter + seed CHANGELOG.

5. **Config-unit.** Create the file-group meta-folder(s) (e.g. `celer/.config-rules/`,
   `arcus/.config-rules/`) with RULES+CHANGELOG governing build/config files.

6. **Gate + workflow.** Fold the changelog/version check into `reviewVueConventions` +
   `reviewArcusCode`; add the `/commit` + create-pr checklist step.

**Phase A.7 — testing buildout (in pendulum; tracked in `TESTING-IMPLEMENTATION.md`)**
Insert before Phase B so the manifest classifies a complete tree (test files included) and
the sync gate can verify behaviorally. Vitest + Testing Library + MSW + Playwright (celer),
Pest (arcus), co-located specs, integration tier, e2e as its own unit, `make unit`/`make e2e`
working. Plan: `cortex/sync/TESTING-IMPLEMENTATION.md`; shape: `TESTING-STRATEGY.md`.

**Phase B — sync engine (in pendulum)**
0. **(B0) Harden the A.6 gate first** (locked decision 6). Fold the changelog/version
   assertion into `reviewVueConventions` + `reviewArcusCode`: for every unit whose code
   changed on the branch, assert a new CHANGELOG entry exists and `RULES.md version:` equals
   the CHANGELOG top entry. The decision-4 triage depends on these versions being enforced.
1. Add `MODE` column to `manifest.tsv` (OVERWRITE / MERGE / LOCAL / SKIP); classify the
   full tree including `src/`, API, config-unit, **and the test layer** (co-located specs +
   infra = MERGE; `celer/e2e/` = its own MERGE unit). Glob-based, first-match-wins, no
   implicit default (locked decision 1).
2. Build the MERGE engine in `sync.py` (greenfield — today's tool only scans the AI layer):
   extend the scan to `celer/` + `arcus/`; shared transform engine; `--mode overwrite` writes
   to the target worktree; `--mode merge` regenerates a checked-out `pendulum-upstream` and
   commits (then stops — human merges, per locked decision 2). Add the version-triage table
   output. Keep `--dry-run`. **Also:** the **source-green precondition** — refuse to generate
   `pendulum-upstream` unless the synced SHA's CI run is green (`gh api`; `--force` overrides;
   locked decision 3). ✅ Shipped (B2b): `enforce_source_green()` gates every real sync;
   states GREEN/RED/PENDING/NO_CHECKS/UNPUSHED/UNKNOWN; pure decision logic unit-tested,
   block behaviour proven hermetically. ✅ Vestigial `trainingbeta` mappings dropped from `specialize()` (and
   the `trainingbeta` examples in the commit/publish-pr commands replaced with pendulum's own
   Auth/User + arcus/celer names). *(No test/config token work — verified non-issue, see
   locked decisions.)*
3. Adapt `/setup-project` to call the shared transform engine (first-run; deletes unused
   modules **and prunes their full test surface** — vitest `moduleSubAliases`, playwright
   project list, MSW handlers, `arcus/tests/Feature/Modules/<X>/`, `celer/e2e/<x>/`) instead
   of renaming by hand.
4. **Ship CI** (`.github/workflows/`) that runs the full suite (lint + unit + integration +
   e2e + arcus Pest) as a `MERGE` artifact so every project inherits green-gating. Pendulum's
   own CI additionally runs the pendulum-only meta-tests.
5. Write `cortex/sync/RUNBOOK.md`: T0 baseline + Tn update commands, copy-paste, **including
   the source-green precondition, the post-merge `make unit`/`make e2e` gate, and the
   intentional-divergence semantic.**

**Phase C — prove on zion**
6. Establish T0 baseline on zion: seed `pendulum-upstream` from transform(bootstrap),
   verify against zion's `88038fa`, first merge into main.
7. **The first Tn into zion is the ideal proof — and it's special.** zion was bootstrapped
   *before* tests existed, so its baseline has none; the first sync delivers the **entire
   test suite as a pure-additive delta** → should merge near-clean, then end in a full green
   run = textbook behavioral proof. It is *also* where intentional-divergence first bites for
   real: zion's `Convention` feature and any component it forked will make pendulum's
   merged-in tests go red against it — confirm that surfaces as expected and is handled per
   the RUNBOOK (adapt zion's copy or accept divergence; never revert the fork). Script this
   case explicitly.

**Phase D — fan out**
8. Roll the T0 baseline to remaining projects once the zion cycle is proven.
