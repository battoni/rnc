# Cortex Sync — Runbook

**Status: verified (2026-06-02).** Covers the engine that ships today — B2a (MERGE engine),
B2b (source-green gate), B2c (triage), and B3 (`--init` first-run + `/battoni-setup-project`). Every
step below is exercised end-to-end by `make sync-test-engine`, `make sync-test`,
`make sync-test-init`, and `make sync-audit`, all green.

How to propagate pendulum's improvements into a downstream project (zion, impressao, …)
without clobbering that project's local changes. Read `DESIGN.md` for the *why*; this is the
*do*. All commands run from the **pendulum** repo unless noted.

---

## Mental model (30 seconds)

- Pendulum is the source of truth. Each file has a **MODE** in `manifest.tsv`:
  `OVERWRITE` (AI layer — written straight to the project), `MERGE` (code/tests/config — goes
  through a vendor branch + `git merge`), `LOCAL` (project owns it — never touched), `SKIP`.
- The MERGE half uses a per-project **`pendulum-upstream`** vendor branch: the engine
  regenerates it to hold pendulum's MERGE files, name-mapped to the project, and **stops**.
  You run `git merge pendulum-upstream` — git does the 3-way merge; conflicts surface only
  where both sides changed the same lines.
- Two safety nets: **git** catches *textual* loss; the **test suite** (run post-merge) catches
  *behavioral* breaks. Green + clean merge = safe.

---

## Commands

```bash
# from the pendulum repo
python3 cortex/sync/sync.py --audit                 # verify the manifest classifies every file
python3 cortex/sync/sync.py --brand-audit           # flag pendulum brand literals in the shipped surface
python3 cortex/sync/sync.py --triage --to ../zion   # preview: what changed per unit, how hard to look
python3 cortex/sync/sync.py --to ../zion --dry-run  # show the sync plan + LOSS REPORT; write nothing
python3 cortex/sync/sync.py --to ../zion            # apply (prompts) — OVERWRITE + regenerate vendor branch
python3 cortex/sync/sync.py --to ../zion --yes      # apply without the prompt
python3 cortex/sync/sync.py --to ../zion --force    # apply, skipping the source-green CI gate
python3 cortex/sync/sync.py --resolve-t0 --to ../zion  # auto-resolve the T0 merge by mode (after `git merge`)
python3 cortex/sync/sync.py --advance --to ../zion     # test gate: run make test-all, advance .cortex-version on green
```

Make targets (pendulum-side checks): `make sync-audit`, `make sync-brand-audit`, `make sync-test`,
`make sync-test-engine`, `make sync-test-init`.

### Safety nets (added after the impressão T0)

The engine no longer relies on unit metadata alone — it has four guardrails against silently
reverting a project's work:

1. **Loss report.** Every sync computes the lines each MERGE file has downstream that pendulum's
   incoming version lacks, prints a summary, writes `.cortex-loss-report.txt`, and folds a warning
   into the confirm prompt. A movable `cortex-presync-<sha>` tag is stamped on the project HEAD for
   one-command rollback.
2. **Junction-aware `--resolve-t0`.** Extension-point files (routes, nav, locales, seeders,
   permission registries, build config) are NEVER auto-taken-from-upstream — they are held for a
   manual keep-both-sides merge. A loud banner fires when a first-time sync lands on a project that
   already extends shared junction files.
3. **`--advance` test gate.** Advancing `.cortex-version` runs `make test-all` and refuses on red
   (a silent revert surfaces as a failing test).
4. **Core/local extension points** (the structural fix — see below).

---

### Retired files, and a CLAUDE.md nobody refreshes

Two things the sync cannot fix by writing, so it reports them on every run:

- **`RETIRED` (pendulum renamed or removed a path).** The sync writes what pendulum has and never
  deletes what it dropped, so a project keeps the old generation beside the new one. Every file that
  matches a `RETIRED` manifest entry is listed. Move any project edits they hold into the current
  file — the old `start-session.md` is where a project's session command usually lives — then run
  the sync again with `--prune-retired`, which deletes them.
- **CLAUDE.md drift.** The always-loaded context is LOCAL, so no sync ever touches it. The run lists
  every `/command` it names that no longer exists, every sub-project row whose folder is gone, and
  every sub-project folder it does not mention. Fix it by hand; there is nothing to merge.

## Core/local extension points

The structural fix for the silent-revert class of bug: shared "junction" files that projects
routinely extend are split into a synced **core** half and a project-owned **local** half. The
local files are `LOCAL` in the manifest, so the sync NEVER touches them — your wiring cannot be
reverted, by construction. Register project wiring in the local half:

| Extension point | Core (synced) | Local (project-owned, `LOCAL`) |
| --- | --- | --- |
| Frontend routes | `src/libraries/router/routes.ts` | `routes.local.ts` (default-exported array, spread in) |
| Navbar items | `TheNavbar.vue` | `TheNavbar/navItems.local.ts` (`localNavItems`, `localNavSettings`) |
| i18n messages | `src/locales/*.json` | `src/locales/local/*.json` (deep-merged at i18n setup) |
| API routes | `routes/api.php` | `routes/api.local.php` (required; declare your own `use`) |
| Seeders | `DatabaseSeeder.php` | `database/seeders/seeders.local.php` (`$this->call(...)` in scope) |
| Permission groups | `PermissionGroupsHelper.php` | `Auth/Helpers/permission-groups.local.php` (`return [...]`, appended) |
| Keys the Manager is denied | `ManagerSeeder.php` | `Auth/Helpers/manager-exclusions.local.php` (`return ['key', ...]`, merged into the core exclusions) |
| Keys the member is denied | `ManagerSeeder.php` | `Auth/Helpers/user-exclusions.local.php` (`return ['key', ...]`, merged into the core member exclusions) |
| Make targets | `Makefile` | `Makefile.local` (`-include`) |

Pendulum ships these as empty stubs; a fresh clone (`--init`) gets them. **A project that
predates the split does a one-time migration:** create the `*.local.*` files and move its
feature wiring out of the core files into them (the loss report tells you exactly what to move).
After that, syncs to those files never conflict again. Patterns matched as `LOCAL`: `*.local.ts`,
`*.local.php`, `*.local.json`, `Makefile.local`, `*/locales/local/*`. **Missing stubs are
auto-created** on the next sync (create-once, never overwritten), so the synced core's static
imports always resolve.

**The one boundary that is NOT split: module build aliases.** Registering a feature module
(`@CatalogModule`) still needs its alias in `vite.config.ts` (bundler) **and** `tsconfig.app.json`
(type-checker). `tsconfig` JSON `paths` can't be cleanly merged via `extends`, so a half-split
(vite-only) would be worse than none — both stay **junction** files instead. That's fine: adding a
module touches these two shared files directly, but the engine net protects them — they are in
`JUNCTION_PATTERNS` (never auto-reverted at T0) and any local alias shows up in the loss report.

---

## Per-project setup (once)

The project repo needs `.cortex-sync.conf` at its root:

```
PROJECT=zion
APP=app.zion
API=api.zion
```

`.cortex-version` (the last pendulum SHA synced) is created/advanced by you at the end of each
sync — see below. Both are `SKIP`/`LOCAL`, never propagated.

> **First-run rename is automated.** Right after cloning pendulum and `git init`-ing the new
> repo, run `/battoni-setup-project` — it writes `.cortex-sync.conf`, then calls
> `python3 cortex/sync/sync.py --init` to name-map every path the way a sync does —
> `celer`→`app.<project>`, `arcus`→`api.<project>`, `docs.pendulum`→`docs.<project>`,
> `config/pendulum.php`→`config/<project>.php`, `battoni-explain-pendulum.md` and its `.mdc`
> mirror — while preserving rule filename tokens like `celer-*`, and refusing to run on pendulum
> itself. A folder moves as a folder, so its untracked content (`node_modules/`, `vendor/`,
> `.env`) travels with it.
> You then delete unused modules and make the initial commit. T0 below ties that initial commit
> to pendulum's history as the merge base.

---

## T0 — establish the baseline (once per project)

Run while divergence is still brand-only (the cheap moment). This ties the two histories so
later merges have a real base.

```bash
# 1. In the project repo, confirm .cortex-sync.conf exists (above) and the tree is clean.

# 2. From pendulum, run the sync. It writes OVERWRITE files to the project worktree and
#    creates the orphan `pendulum-upstream` branch holding the transformed MERGE set.
python3 cortex/sync/sync.py --to ../zion --yes

# 3. In the project repo: commit the OVERWRITE layer, then merge the vendor branch in.
cd ../zion
git add -A && git commit -m "cortex T0: overwrite layer"
git merge --allow-unrelated-histories pendulum-upstream

# 4. Auto-resolve the T0 conflicts by mode (one command — see recipe below), then run the suite.
python3 cortex/sync/sync.py --resolve-t0 --to ../zion   # or --dry-run first to preview
cd ../zion && git commit --no-edit                       # the resolver stages; you commit
make test-all                                            # the behavioral gate

# 5. Only after green: record the synced pendulum SHA.
echo <pendulum-sha> > .cortex-version
git add .cortex-version && git commit -m "cortex T0: baseline at <pendulum-sha>"
```

The merge commit in step 3 is what gives git a real merge base from here on.

### What `--resolve-t0` does

T0 is **not** near-empty for an already-diverged project: the empty merge base makes every
MERGE file that differs on both sides a "both added" conflict (~90 on zion). They are
mechanical, not judgement calls, so `--resolve-t0` settles them all by the file's **MODE**:

| Conflicted file's mode | Resolved to | Why |
| --- | --- | --- |
| `MERGE` (code/tests/config) | **upstream** (theirs) | T0 adopts pendulum's version; the project's pre-metadata edits are reconciled here once |
| `OVERWRITE` / `LOCAL` | **project** (ours) | the AI layer is already current (step 3); locks/brand/`.env` are the project's |
| `package.json` / `composer.json` | **union** | upstream base (it carries the test toolchain) + any project-only deps added back |

Files only the project has (its own feature modules, planning docs) are kept automatically (adds,
not conflicts). The resolver **stages** the result and stops — you run the suite, then commit.
Validated on zion: 89 conflicts → 0 unresolved in one command (87 upstream, 2 union).

> **T0 only.** `--resolve-t0` takes upstream for *every* MERGE conflict, which is right only at
> the baseline (the project hasn't intentionally forked code yet). At `Tn` a MERGE conflict means
> both sides edited the same lines — an intentional-divergence judgement call you resolve by hand
> (see below). After T0, `Tn` merges are clean anyway: the T0 merge commit is the shared base, so
> only genuine both-sides edits conflict.

---

## Tn — every later pendulum update

```bash
# 1. Preview. Diverged units need the closest look; clean-update units fast-forward.
python3 cortex/sync/sync.py --triage --to ../zion

# 2. Apply. The source-green gate runs first: it checks pendulum's own CI for the SHA being
#    synced and REFUSES if it isn't green. OVERWRITE files land in the worktree; the LOSS REPORT
#    prints (and writes .cortex-loss-report.txt); a cortex-presync-<sha> rollback tag is stamped;
#    `pendulum-upstream` is regenerated and committed. The engine then STOPS.
python3 cortex/sync/sync.py --to ../zion --yes
#    Commit local first? Then `git push` so CI can run — or pass --force to sync from an
#    unpushed commit you trust (see "The source-green gate" below).

# 3. In the project repo, merge the vendor branch. With the core/local split most files no
#    longer conflict; where they do, KEEP BOTH SIDES — never blindly take upstream (cross-check
#    .cortex-loss-report.txt).
cd ../zion
git merge pendulum-upstream

# 4. Test gate + marker. --advance runs make test-all and advances .cortex-version ONLY on green
#    (a silent revert shows up as a red test). Then commit the marker.
cd ../pendulum
python3 cortex/sync/sync.py --advance --to ../zion
cd ../zion && git add .cortex-version && git commit -m "cortex: sync to <pendulum-sha>"
```

`sync.py` prints these next-steps after it runs, so you don't have to memorise them.

---

## Reading the triage dashboard

`--triage` compares each unit's pendulum `version` against the project's `based-on`:

| State | Meaning | Action |
| --- | --- | --- |
| `unchanged` | project is at pendulum's version | skip |
| `clean-update` | pendulum moved; project never edited this unit | git fast-forwards — low risk |
| `diverged` | both pendulum and the project edited this unit | review the merge carefully |
| `project-ahead` | project edited locally; pendulum has nothing new | nothing to merge |
| `new` | pendulum has a unit the project lacks | additive |

The severity flag (`‼` major / `+` minor / `·` patch) grades pendulum's delta. Each actionable
row prints the unit's **CHANGELOG entries since `based-on`**, so you read *what* changed without
diffing.

---

## The source-green gate (B2b)

Before a real sync, `sync.py` checks **pendulum's own CI** for the SHA it's about to
propagate, and refuses to sync from a source that isn't green. This stops a broken
pendulum commit from fanning out to every downstream project.

| Gate state | Meaning | Outcome |
| --- | --- | --- |
| `GREEN` | all checks passed | sync proceeds |
| `RED` | a check failed | **blocked** — fix CI first (or `--force`) |
| `PENDING` | checks still running | **blocked** — wait for CI (or `--force`) |
| `NO_CHECKS` | the commit has no CI configured | **blocked** (or `--force`) |
| `UNPUSHED` | the commit isn't on the remote yet | **blocked** — `git push` first (or `--force`) |
| `UNKNOWN` | no `gh` / no remote — can't verify | **blocked** (or `--force`) |

- The gate reads the source repo via `gh` (combined commit status + check-runs). It needs
  `gh` installed and authenticated.
- **`--force`** skips the gate entirely (and its network call) — the escape hatch for an
  unpushed local commit you trust, or a repo with no CI. `--dry-run` reports the gate state
  but never blocks (a dry run writes nothing).
- The decision logic is pure and unit-tested (`make sync-test`); the block behaviour is
  proven hermetically in `make sync-test-engine`.

---

## Intentional divergence (important)

A project that deliberately forked a unit's behavior will see pendulum's merged-in **test** fail
against it. **That is the correct signal**, not a bug — the test layer is a *behavioral*
conflict-surfacer complementing git's textual one. When it happens:

- **Adapt the project's copy** (its own version, bumped above `based-on`), or
- **Accept the divergence** and keep the fork.
- **Never** "fix" an intentional fork back to pendulum just because a test went red.

`clean merge + green tests` = safe. `clean merge + red tests` = a real behavioral break to
investigate before advancing `.cortex-version`.

---

## Guarantees & non-guarantees

- The engine **never** writes to pendulum, and never **overwrites** `LOCAL`/`SKIP` files in the
  project. The one narrow exception: it **creates** a missing core/local stub (`*.local.*`,
  `locales/local/*`) so the synced core's static imports resolve — create-once, never overwritten.
- **Sub-project scoping.** `celer`/`arcus` are core and always sync (they are the project's
  app/api). Pendulum's optional siblings (`codelumen`, `liquen`, `vitrum`) sync **only if the
  target already has that directory** — a project that deleted one at bootstrap won't have it
  forced back. The sync report prints how many files were skipped for opted-out sub-projects.
- **Brand / visual identity is `LOCAL` and never overwritten.** `colors.css` (the brand
  contract), `src/components/atoms/ALogo/`, `src/assets/logo/`, `public/favicon/`, and
  `index.html` are project-owned — the sync leaves them untouched. Verify with `--audit`.
- **Brand *content* inside shared files is reconciled, not protected.** Identity *strings* that
  live inside a MERGE file (e.g. a personalized login welcome in `locales/*.json`) can't be
  file-classified. At T0 they revert to pendulum's neutral text and surface in the divergence /
  triage output — **re-apply your wording from there** (the accepted approach; no auto-machinery).
  Keep such strings brand-neutral in pendulum so a revert is harmless, never a wrong-brand leak.
- It writes OVERWRITE directly and stages MERGE on the vendor branch — it **stops before the
  merge**; the merge and conflict resolution are yours (human judgment).
- A plain sync does **not** advance `.cortex-version`. You advance it after the merge, with
  `--advance`, which runs `make test-all` and writes the marker only on green (`--force` skips the
  gate and says so). Nothing advances it on its own.
- Dependency lock files (`composer.lock`, `package-lock.json`, …) are `LOCAL`: each project owns
  its own; regenerate with `npm install` / `composer install` after a sync if dependencies moved.

---

## Status of the build

The sync engine is feature-complete for the designed scope:

- **[B2a]** MERGE engine (vendor branch + `git merge`) — `make sync-test-engine`.
- **[B2b]** Source-green gate (`gh` CI check; `--force` override) — `make sync-test` (logic) +
  `make sync-test-engine` (block behaviour).
- **[B2c]** Triage dashboard — `make sync-test-engine`.
- **[B3]** `/battoni-setup-project` → `sync.py --init` first-run rename — `make sync-test-init`.
- **[post-merge gate]** `--advance` — runs `make test-all` in the target and advances
  `.cortex-version` only on green.

The source-green gate reads pendulum's own CI, so it has a real signal once commits are pushed.
The suite counts at each point live in the body of the commit that closed the batch
(`shared-conventions`), not here, where they went stale (this section once said 117 / 24 / 72).

Out of scope, on purpose: a `--merge` convenience that runs the git merge for you — the merge is
human judgment. **Retired:** "auto-advancing `.cortex-version` after a verified-green merge" as a
future idea — `--advance` does it, started by a person, and that is the intended shape.
