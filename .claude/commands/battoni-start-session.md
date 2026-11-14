# Start Session

Load the right rules before planning or working on a feature.

**Usage:** `/battoni-start-session` · `/battoni-start-session <id>` (e.g. `rnc-1`) to build
the next phase of a feature (see `.claude/feature-flow.md`).

## First, either way — the sweep

`rtk make wt-gc`: tears down every worktree whose PR merged and that is clean and quiet (the
folder and the branch), and lists the rest as `kept` (the failed check, e.g.
an open PR) or `left behind` (PR closed unmerged, or no commit and no session for 7 days) with
its discard dry run, `rtk make wt-discard ID=<id>`. Say the counts in one line; a left-behind one
is never discarded here, `/battoni-close-session` asks about it.

## With a feature ID — build one phase

0. **Be in its worktree.** From the main checkout, `EnterWorktree` with
   `path: .claude/worktrees/<id>` (the `Worktree:` line of its `state.md`). No worktree yet (made
   before this flow, or removed after a close): `rtk make wt-new ID=<id> BRANCH=<its branch>`,
   then enter it. Never check the branch out in the main checkout.
1. Read `specs/<id>-*/spec.md`, `plan.md` and `state.md`. Nothing else about the feature:
   **never `proposal.html`**, never an old conversation.
2. Load the rules of the sub-projects the next phase touches (Step 2 below) and the folder
   `RULES.md` chain of the files it edits.
3. Check that the worktree is on `feature/<id>-<slug>` (`rtk git branch --show-current`). A
   phase that needs a browser needs its environment: `make fresh-seed`, `make serve-api` and
   `make serve-app` from the worktree, with the main stack stopped (no `wt-up` in rnc, see
   `.claude/feature-flow.md`, "Worktrees"). Confirm in one line: the phase, the A# it proves, the next step
   from `state.md`.
4. Build only that phase: write the tests and **see them fail**, write the code, see them pass,
   the phase gate (`.claude/feature-flow.md`: `/battoni-pre-commit` without the full Playwright
   suite, only the e2e files this phase adds or edits). A phase with a `looks like:` list builds
   the screen to it. Do not commit yet.
5. Rewrite `state.md` (Done with suite counts and `pre-commit green <date>`, Next with the exact command, Pitfalls found —
   a decision taken while building also goes into `spec.md` as `C#`). Set `Stage: build F<n>/<total>`.
   Then **one** commit with the code, `state.md` and any `spec.md` change, subject
   `<id>: F<n> …` (`/battoni-run` finds a done phase by it). Never a separate "Record F<n>" commit.
6. Stop. Tell the owner: `/clear`, then `/battoni-start-session <id>` for the next phase, or
   `/battoni-verify <id>` after the last one.

A question only the owner can answer stops the phase; write it in `state.md` → Next before asking.
In the away mode (`/battoni-run`) nobody can answer: take the recommended option and log it as `W#`
(`.claude/feature-flow.md`, "Decided while you were away"); only a hard stop from that section stops the phase.

## Without an ID

### Step 1 — Ask which sub-projects

The sub-projects are the rows of the **Sub-projects** table in the root `CLAUDE.md` (in Cursor,
`project-context.mdc`), already in context — never a list written in this file. Ask the user which
of them (plus `cortex` when it has no row of its own) they are working on, offering those folder names.

### Step 2 — Load rules

**Always** — read `.claude/rules/shared-conventions.md` (it applies to every sub-project).

Then by the row's **Type** in that table:

- **Laravel API** (`api.rnc/`) — read `.claude/rules/arcus-api-architecture.md`.
- **Vue 3 SPA** (`app.rnc/`) — read all nine files (the canonical set is `celer-01` through `celer-09`):
  - `.claude/rules/celer-01-vue-checklist.md`
  - `.claude/rules/celer-02-vue-imports.md`
  - `.claude/rules/celer-03-vue-script.md`
  - `.claude/rules/celer-04-vue-template.md`
  - `.claude/rules/celer-05-module-conventions.md`
  - `.claude/rules/celer-06-design-system.md`
  - `.claude/rules/celer-07-view-patterns.md`
  - `.claude/rules/celer-08-rules-auto-injection.md`
  - `.claude/rules/celer-09-brand-mark-contract.md`
- **A documentation portal** (VitePress, `docs.*`) — load the `docs-portal` skill.
- **An Astro site** — first read the row's own `<dir>/CLAUDE.md`, if there is one. Load the
  `landing-page` skill only when the row's description says it is a marketing or landing site: a
  child's Astro site can be a noindex, non-marketing site, and that skill pushes SEO/AEO/GEO,
  `llms.txt` and conversion. Otherwise no rules; say so in Step 3.
- **Anything else** (`cortex`, a handbook, design tokens) — no rules configured yet. Say so and
  proceed without loading rules.

### Step 3 — Confirm

Reply with a single line confirming what was loaded. For example:

> Loaded shared + api.rnc + app.rnc rules. Ready — what are we building?

Or if a sub-project has no rules yet:

> No rules configured yet for cortex. Ready — what are we building?

A new request starts by picking its track (`.claude/feature-flow.md`, "Three tracks"): a feature
goes to `/battoni-intent`.

Reads stay in the main checkout. **Before the first edit** of a session without an ID (a fix, a
review fix, a QA helper), move into a worktree: `rtk make wt-new ID=<name> BRANCH=<branch>`, then
`EnterWorktree` with `path: .claude/worktrees/<name>`. The main checkout stays on `main`.
