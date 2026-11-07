# Review Vue Conventions

> This command reviews Vue/TS files in **app.rnc/**. Run from the rnc monorepo root.

Analyze all app.rnc changes in the current branch against `main`, identify every convention violation, and fix them.

**This command runs in plan mode. Do not edit any file until the plan is approved.**

---

## Step 1 — Read the rules

Before doing anything else, read all nine app.rnc rule files (the canonical set is `celer-01` through `celer-09`) and the shared conventions:

- `.claude/rules/celer-01-vue-checklist.md`
- `.claude/rules/celer-02-vue-imports.md`
- `.claude/rules/celer-03-vue-script.md`
- `.claude/rules/celer-04-vue-template.md`
- `.claude/rules/celer-05-module-conventions.md`
- `.claude/rules/celer-06-design-system.md`
- `.claude/rules/celer-07-view-patterns.md`
- `.claude/rules/celer-08-rules-auto-injection.md`
- `.claude/rules/celer-09-brand-mark-contract.md`
- `.claude/rules/shared-conventions.md`

## Step 2 — Get the diff (committed + uncommitted + untracked)

This command runs inside `/battoni-pre-commit`, **before** a commit — so it must review work that is not committed yet. Do **not** rely on `main...HEAD` alone; that only sees committed work and silently misses staged, unstaged, and new files.

Compute the fork point once, then union three sources, filtered to app.rnc TS/Vue files (`.ts`, `.mts`, `.tsx`, `.vue`):

```bash
BASE=$(git merge-base main HEAD)
git diff "$BASE" HEAD   --name-only -- app.rnc/   # committed on this branch
git diff                --name-only -- app.rnc/   # staged + unstaged
git ls-files --others --exclude-standard -- app.rnc/   # untracked new files
```

Take the **union** of those three lists (de-duplicated). For each file, diff it against the fork point so you review what the branch **plus your uncommitted work** introduced — not noise from `main` moving ahead:

```bash
git diff "$BASE" -- <file>   # for tracked files; read untracked files in full
```

Read every file in the union in full.

## Step 3 — Read the folder RULES.md chain

For each changed file, read the `RULES.md` chain from the file's folder up to `app.rnc/src/` (see `celer-08-rules-auto-injection`). **Closer `RULES.md` files override the global rules.** A convention defined in a folder `RULES.md` is binding for files under it and is not optional.

## Step 4 — Find violations

Go through every added line in every changed file. Check each line against the global rules **and** the folder `RULES.md` chain. Note every violation: what it is, which rule it breaks, what the fix is.

**Test coverage:** a changed unit (`.vue` component, service, store, or composable with a bumped `CHANGELOG.md`) should have or gain a co-located spec — `*.spec.ts` for unit, `*.integration.spec.ts` for view HTTP flows. Flag any unit modified without a corresponding test as a violation. See the `celer-testing` skill for `mountWithPlugins` / `renderWithPlugins` patterns.

**Changelog/version gate (per-unit metadata — blocking).** For every **unit** whose code changed on the branch — the nearest enclosing folder that holds both `RULES.md` and `CHANGELOG.md` (component, view, module, or the `.config-rules` config-unit) — assert all three:

1. **A new CHANGELOG entry exists.** If any non-metadata file in the unit changed (`.vue`, `.ts`, spec) but the unit's `CHANGELOG.md` has no new top entry on this branch, that is a violation. Detect it: a unit whose code changed but whose `CHANGELOG.md` is *not* in the diff (`git diff main...HEAD --name-only -- <unit>/CHANGELOG.md` is empty) fails the gate.
2. **Versions match.** The `version:` in the unit's `RULES.md` frontmatter must equal the top entry version in its `CHANGELOG.md`. A mismatch is a violation.
3. **`based-on` tracks `version` (rnc-origin units).** For any unit whose `RULES.md` frontmatter has `origin: rnc`, `based-on:` must equal `version:`. They are bumped together; a stale `based-on` (`< version`) makes the cortex sync triage read the unit as falsely **diverged** downstream. Units with a different `origin` are exempt — their `based-on` tracks the upstream they were forked from.

Flag each as `[A.6 — missing changelog]`, `[A.6 — version mismatch]`, or `[A.6 — based-on drift]` with the unit path and the expected fix (add the entry / align the frontmatter / set `based-on` to `version`). These are **blocking, not cosmetic**: the cortex propagation triage reads these versions to decide how each unit merges downstream, so a missed bump, a mismatch, or a stale `based-on` corrupts the triage.

### Review lenses (beyond conventions)

Conventions are the floor. Also pass the diff through these lenses and tag each finding with a severity — 🔴 critical / 🟠 high / 🟡 medium / 🟢 low:

- 🚀 **Performance** — unkeyed or badly-keyed `v-for` (index as `:key`), heavy work in a `computed` that reruns often, a default `import _ from 'lodash'` where `lodash-es` (tree-shakeable) is meant, large watchers doing sync work. Flag with `[🚀 perf]`.
- 🔒 **Security** — `v-html` with anything not provably sanitized (XSS), rendering user input as a URL/`href` without scheme checks, secrets/tokens hardcoded in the client, PII logged to console. Flag with `[🔒 security]`.
- 🩹 **Root cause (fix branches)** — if this branch fixes a bug, is it the real fix or a band-aid (a `try/catch` hiding the error, a `?.` where the value should never be null, a `setTimeout` masking a race)? Flag band-aids with `[🩹 root-cause]`.

## Step 5 — Present the plan

Output a structured fix plan, grouped by file. Be specific — each item must name the rule (or lens) and describe the exact change. Prefix each with its severity where a lens applies:

```markdown
### src/modules/Auth/components/MLoginEmail/MLoginEmail.vue
- Line 3: [02 — module imports] `@AuthModule/services` → `@AuthModule`
- Line 58: [04 — multiline tag] closing `>` must be on its own line; content on next indented line
- Line 61: [04 — Tailwind order] classes need Prettier pass

### src/modules/Auth/services/verifyCode.service.ts
- Line 1: [05 — service pattern] missing `export default async function`
```

If there are no violations, state **✅ No violations** and stop.

**Do not make any changes yet.** Wait for approval.

## Step 6 — Execute (after approval)

After the plan is approved, apply every fix listed. Work file by file. Do not introduce any changes beyond what is listed in the plan.

## Step 7 — Sort locales

After all fixes are applied, run:

```bash
cd app.rnc && npm run sort-locales
```

This re-sorts all locale files according to the COMMON/COMPONENT/MODULE grouping and one-liners-first convention. Always run it last, even if no locale files were changed — it is idempotent.
