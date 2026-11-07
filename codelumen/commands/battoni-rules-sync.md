---
title: /battoni-rules-sync
outline: deep
---

::: info Generated page
This page is generated from `.claude/commands/battoni-rules-sync.md` — run it with `/battoni-rules-sync`. **Edit the source command, not this page.**
:::

# Rules Sync (unit RULES.md freshness gate)

> Keep each **unit's `RULES.md`** in sync with the code changed on the current branch, and keep the unit's **version + `CHANGELOG.md`** aligned with it. This is the stack-aware freshness engine for both `app.rnc` (Vue 3) and `api.rnc` (Laravel). It complements the review commands: they check that the version metadata was bumped; this checks (and can fix) that the RULES.md **content** still describes the code, then drives the version bump.
>
> **Two modes.** Default **auto-updates** stale/missing `RULES.md` (+ bumps version & CHANGELOG) — writes to the working tree, never commits. `--check` is **read-only** and only **reports** stale/missing/unbumped units (a blocking backstop for `/battoni-pre-commit` and `/battoni-publish-pr`).
>
> **Usage:** `/battoni-rules-sync [app.rnc|api.rnc] [--check]`. Run from the rnc monorepo root. No sub-project → do both.

---

## Step 0 — Resolve scope, mode, roots

| sub-project | source root(s) | source file types | unit stack |
| --- | --- | --- | --- |
| `app.rnc` | `app.rnc/src` | `.vue` `.ts` `.mts` `.tsx` | Vue 3 |
| `api.rnc` | `api.rnc/app`, `api.rnc/database` | `.php` | Laravel |

- `--check` → **read-only** (no writes at all); default → auto-update.
- If the tree is clean and there are no commits ahead of `main`, there is nothing to sync → report "no change set" and stop.

## Step 1 — Compute the change set (committed + uncommitted + untracked)

Union three sources against the fork point, so uncommitted work is included (this runs before a commit):

```bash
BASE=$(git merge-base main HEAD)
git diff "$BASE" HEAD --name-only        # committed on this branch
git diff --name-only                     # staged + unstaged
git ls-files --others --exclude-standard # untracked
```

Keep only paths **under a source root** with a **source file type** for the sub-project. **Exclude** `RULES.md`, `CHANGELOG.md`, tests, `vendor/`, `node_modules/`, build output, generated files. Empty set → report "nothing to sync" and stop.

## Step 2 — Map each changed file to its unit

For each changed source file, its **unit** = the **nearest ancestor folder that holds a `RULES.md` + `CHANGELOG.md` pair**, searching upward but not above the source root (a component, view, module, or architectural layer). Record per unit: the folder, its `RULES.md`/`CHANGELOG.md` paths (or **none**), and whether either file is itself in the change set.

## Step 3 — Classify staleness (per unit, stack-aware)

A `RULES.md` is **stale** when a change touches the **documented contract** for that stack. Read the changed file's diff and the unit's `RULES.md`, then decide:

**Vue (app.rnc)** — stale if the change adds/removes/renames a **prop, emit, or slot**; changes a **service** the unit calls or its payload; changes a **documented decision / edge case / dependency** the rule names.

**Laravel (api.rnc)** — stale if the change adds/removes/renames a **public method or route**; changes a **response shape** (the `ApiResponse` envelope / returned fields / a Resource); changes a **repository method** the rule names; adds/changes an **Enum** or **semantic const** the rule documents.

Outcome per unit:
- **`RULES.md` (or `CHANGELOG.md`) already in the change set** → the developer handled it; confirm it matches and mark **ok**.
- **Contract changed, `RULES.md` NOT in the change set** → **stale** (blocking).
- **Internal-only change** (no contract impact) → **advisory** only.
- **No `RULES.md`/`CHANGELOG.md` on the unit** → **missing** (blocking).
- **Code changed but version/CHANGELOG not bumped this branch** (even if content is still accurate) → **unbumped** (blocking) — see the review commands' `[A.6]` gate.

## Step 4a — Default mode (auto-update, writes)

- **stale** → refresh the unit's `RULES.md` so it matches the new code (delegate to `/battoni-generate-component-rules` for app.rnc or `/battoni-generate-api-rules` for api.rnc via their **UPDATE** path — preserve human edits, change only the drifted sections), then **bump the version**:
  - Pick the SemVer bump per `cortex/sync/SCHEMA.md`: **MAJOR** for a breaking contract change, **MINOR** for additive, **PATCH** for clarification.
  - Add a new top entry to the unit's `CHANGELOG.md` (canonical source), then set `RULES.md` frontmatter `version:` to equal it. For `origin: rnc` units, set `based-on:` = the new `version:` too (they track together in rnc itself).
- **missing** → create `<unit>/RULES.md` + `<unit>/CHANGELOG.md` at `1.0.0` via the generator's CREATE path (frontmatter + sibling CHANGELOG per SCHEMA).
- **unbumped** → content is fine; just add the CHANGELOG entry + bump `version:` (and `based-on:`) to match.
- **advisory** → leave the files; note it in the report.
- Leave every written file in the **working tree** — `/battoni-commit` stages them in the `docs` / RULES.md group. This command **never commits**.

**Before finishing, re-check every file you wrote — lean · accurate · necessary.** This can auto-write on a pre-commit, so a sloppy line ships silently:

- **Accurate** — re-read the changed code; every added line matches what the code actually does now (real prop/emit/slot names, real routes/methods, the real `ApiResponse` shape). The code wins.
- **Necessary** — only the durable contract the change affected. Cut investigation narrative and anything a global `celer-*` / `arcus-*` rule already says.
- **Lean** — still within budget (**≤ ~600 words / ~4 KB**). Over budget → **split** (a child-folder `RULES.md`, closest-folder-wins), never truncate. A diff that only *adds* lines is a smell — replace the stale line, don't append beside it.

## Step 4b — `--check` mode (read-only, no writes)

Write **nothing** — not even a version bump. Produce a blocking list: every **stale**, **missing**, and **unbumped** unit, with the file that triggered it and what needs documenting/bumping. This is the signal `/battoni-pre-commit` and `/battoni-publish-pr` use to stop.

## Step 5 — Report

Per unit: **ok** / **updated** / **created** / **bumped** / **stale** (check) / **missing** (check) / **unbumped** (check) / **advisory**, with the unit path and the triggering file. End with a verdict:
- Default mode: how many units were updated/created/bumped — now uncommitted in the working tree. For each written file, show what you added in one line and confirm the lean · accurate · necessary re-check (flag any that had to be **split**).
- `--check` mode: **PASS** (nothing stale/missing/unbumped) or **BLOCK** (list them).

---

## Rules

- **`--check` writes nothing** — auto-update and version bumps happen only in default mode.
- **Scope is the change set only.** Never touch a unit with no changed code. Never mass-refresh.
- **CHANGELOG.md is canonical; frontmatter `version:` mirrors it** — never bump one without the other (the `[A.6]` gate enforces equality).
- **Never commit or push.** Written files stay in the working tree for `/battoni-commit`.
- Match each stack's `RULES.md` format — don't impose the Vue shape on a Laravel unit or vice-versa.
- **Keep every file lean** (≤ ~600 words / ~4 KB) — durable contract only, no refinement-chat narrative. Over budget → split, don't grow. See `celer-08-rules-auto-injection`.
