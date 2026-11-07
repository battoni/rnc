# Setup Project

Initialize a new project cloned from rnc: transform the bootstrap into your project's
names and trim what you don't use. Run this once, right after cloning and `git init`.

**This command runs in plan mode. Do not edit any file until the plan is approved.**

---

## Step 1 — Gather project info

Ask the user the following, one at a time:

1. **Project name** — a lowercase slug (e.g. `zion`, `acme`). This becomes `PROJECT`; the
   frontend becomes `app.<project>` and the API `api.<project>`.
2. **Frontend (`app.rnc`)** — used? (If yes it is renamed to `app.<project>`.)
3. **API (`api.rnc`)** — used? (If yes it is renamed to `api.<project>`.)
4. **Other sub-projects** — `codelumen` (docs), `vitrum` (site), `liquen` (design tokens):
   keep or remove each? (These keep their own names — the transform only renames app.rnc/api.rnc.)
5. **Feature modules to remove** — the bootstrap ships app.rnc modules `Auth`, `User`, `Home`,
   `Showcase` (and api.rnc `Auth`). List any you don't want; each gets deleted **and its full
   test surface pruned** (Step 2c).

---

## Step 2 — Build the plan

Produce a plan with three parts. Present it and **wait for approval** before changing anything.

### a. Config + transform (the shared engine does the renaming)

- Create `.cortex-sync.conf` at the repo root — this is what every later `cortex/sync` run reads:

  ```
  PROJECT=<project>
  APP=app.<project>
  API=api.<project>
  ```

- Run the shared transform engine. It rewrites every `app.rnc`/`api.rnc`/`rnc` word in **file
  contents** — rule globs, `CLAUDE.md`, `project-context`, command paths, hook patterns, all of
  it — and name-maps **paths** the same way a sync does: `app.rnc/`→`app.<project>/`,
  `api.rnc/`→`api.<project>/`, `docs.rnc/`→`docs.<project>/`, and any file whose own name
  carries the word (`config/rnc.php`, `battoni-explain-rnc.md` + its `.mdc` mirror).
  `celer-`/`arcus-` rule **filenames** are preserved. No more editing globs by hand:

  ```bash
  python3 cortex/sync/sync.py --init --dry-run   # preview the renames + file count
  python3 cortex/sync/sync.py --init             # apply
  ```

### b. Remove unused sub-projects

For each unused sub-project (`codelumen`, `vitrum`, `liquen` — or `app.rnc`/`api.rnc` themselves if
not used), delete:

- the sub-project folder,
- its `.claude/rules/*` and `.cursor/rules/*` files,
- its `.claude/skills/*` and `.claude/commands/*` (e.g. `battoni-review-arcus-code.md` and `battoni-generate-api-rules.md` if api.rnc is dropped),
- its row — and any path into it — in `CLAUDE.md` and `.cursor/rules/project-context.mdc` (both
  are project-owned and keep identical bodies).

What stays is the synced layer (`.claude/`, `.cursor/`), and it is written to survive this step:
a synced rule, command or skill may mention `codelumen/` only as "if the project keeps it",
never as the place a rule lives. After deleting, `grep -rn "codelumen/" .claude .cursor` should
show nothing but those conditional mentions.

### c. Remove unused feature modules — prune the FULL test surface

This is the easy-to-miss part: a module's co-located specs vanish when you delete its folder,
but things **outside** the folder still reference it. For each removed module `<X>` (e.g.
`Showcase`):

- delete `app.<project>/src/modules/<X>/` (co-located unit + integration specs go with it), then prune:
  - `app.<project>/vitest.config.ts` — the `moduleSubAliases` entry (`@<X>Module`),
  - `app.<project>/playwright.config.ts` — any per-module project entry,
  - `app.<project>/src/test/msw/handlers.ts` — the module's mock endpoints,
  - `api.<project>/tests/Feature/Modules/<X>/` — its api.rnc feature tests (if any),
  - `app.<project>/e2e/<x>/` — its e2e specs (if any),
  - its `.claude/rules` / `.cursor/rules` folder-glob entries, if module-specific.

---

## Step 3 — Execute (after approval)

1. Write `.cortex-sync.conf` (Step 2a).
2. Run `python3 cortex/sync/sync.py --init`.
3. Delete unused sub-projects and their tooling (Step 2b).
4. Delete unused feature modules and prune their full test surface (Step 2c).
5. Sanity-check what remains:
   - `python3 cortex/sync/sync.py --audit` — every file still classifies,
   - `make unit` and `make pest` — green for the kept modules.

---

## Step 4 — Confirm

Report what was renamed, removed, and pruned. End with:

> Project configured as **<project>**. `.cortex-sync.conf` is set, so future rnc updates
> flow in via `cortex/sync/RUNBOOK.md`. Run `/battoni-start-session` to begin.
