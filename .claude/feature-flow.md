# Feature flow — intent → spec → plan → build → verify

The reference the `/battoni-intent`, `/battoni-spec`, `/battoni-plan`, `/battoni-start-session <id>`,
`/battoni-verify` and `/battoni-run` commands follow. Read only the section you need.

Adapted from Anthropic's AI-native SDLC playbook (intent → spec → plan, each stage committing a
file) and kept the Battoni visual proposal, where the owner decides by seeing the screen.

## Three rules

1. **Every stage ends in a small file, then `/clear`.** The next session starts by reading that
   file, never the previous conversation (the away mode chains rows 1–3 and hands rows 4…last to
   fresh subagents instead — "Two modes"). Long sessions are what cost the most (pergula, week of
   24/10: 229k average context per call, 43% of calls above 200k).
2. **Nothing is built without knowing how to prove it works.** The spec carries acceptance
   criteria; they become failing tests before the code. Stop when the tests pass, not when it
   feels done.
3. **The owner picks, never types.** Every question to the owner goes through the
   `AskUserQuestion` tool: a short `header`, 2–4 concrete options with a `label` and a one-line
   `description` of what choosing it implies, the recommended option first and marked
   `(recommended)`. The tool adds the last option, "Other", where the owner types a free answer —
   never add your own "other" option. Up to four questions per call (one round); more go in the
   next round. `multiSelect: true` only when the choices are not exclusive. Plain typed questions
   only for what cannot be optioned ("paste the response shape", "send the file"). Replaces the
   numbered text list (owner, 03/11/2026): answering "1A, 2D" by voice was workable, selecting is
   faster and leaves no dictation errors.

## Three tracks — pick one first

| Track | When | What it produces |
| --- | --- | --- |
| **Trivial** | A text, a colour, a typo. No decision, no behaviour change. | Nothing. Do it, `/battoni-pre-commit`, commit. No ID. |
| **Small** | A bug, or one rule that changes. No new screen, no open product decision. | An ID. A five-line intent at the top of the PR body. Plan mode with the tests that will prove it; red → code → green; a clean-context reviewer. No folder. |
| **Feature** | A new screen, a product decision, more than one phase, or a client must approve. | An ID and a folder `specs/<id>-<slug>/` with the files below. |

In doubt, ask the owner which track (rule 3).

## Task IDs

- **Format:** `<TASK_PREFIX>-<n>`, e.g. `rnc-14`. Speakable: "open rnc-14".
- **Prefix:** the `TASK_PREFIX=` line of `.cortex-sync.conf` (SKIP in the sync manifest, so each
  project keeps its own). Missing → use `PROJECT=` and suggest adding the line. The bootstrap has no `.cortex-sync.conf`
  at all: there the prefix is the repository's folder name.
- **Next number:** one above the highest `<prefix>-<n>` found in any of these. All of them live in
  git, which every worktree shares:
  ```bash
  { git ls-tree -d --name-only main specs/; git branch -a --format='%(refname:short)'
    git log --all --format=%s; } | grep -oE '<prefix>-[0-9]+' | sort -t- -k2 -n | tail -1
  ```
  (`specs/` on `main`, every branch name, every commit subject — the small track leaves only the
  last two.) Nothing found → `1`. The ID is claimed at once: `/battoni-intent` creates the branch in the same step (in its
  own worktree, `rtk make wt-new`), so a second worktree sees it.
- **Where it appears:** folder, branch (`feature/rnc-12-sidebar-menu`, `fix/rnc-14-…`),
  commit subjects and PR title (`rnc-12: Add the Settings index page`).
- **Who gets one:** feature and small. Trivial never. A client's own ID (Jira, Trello) replaces it.
- Work delivered before the flow keeps no ID; nothing is renumbered.

## The feature folder — `specs/`

Versioned (owner, 03/11/2026): the folder travels in the feature's branch with the code, so any
worktree has it, every change has history, and the PR shows the spec next to the diff. `plans/`
stays private (owner context, cross-feature decisions, investigations). **The personal-data rule
holds here as in code:** invented examples only, field names never values, never a real record.
Each command commits its file on the feature branch (`<id>: Add the spec`, `<id>: Update state`);
a build phase makes **one** commit, `<id>: F<n> …`, carrying its code and its `state.md` update,
which is how `/battoni-run` tells a done phase on resume. In verify, a `state.md` line rides with
the commit of the work it records; a step that changes no file (a review with nothing applied, a
round that passed) is recorded in the next commit, at the latest the `delivered` one.
*Retired 10/11/2026: a "Start F<n>" and a "Record F<n> in state" commit around every phase.*
boanerges-3 made 44 commits, 3 per phase; a phase with no `F<n>` commit is already the next one on
resume, so the extra two only added noise.
In the cortex sync manifest `specs/` is `LOCAL`: every project keeps its own.

```
specs/rnc-12-sidebar-menu/
├── intent.md      # the request — no solution
├── facts.md       # the research (written by a Sonnet subagent)
├── spec.md        # the truth: R#, acceptance criteria A#, decisions D#
├── plan.md        # phases that fit one session each
├── state.md       # where we stopped — the permanent handoff
├── qa-script.md   # the QA steps (Sonnet, at verify): Chrome runs it, the human doc is made from it
└── proposal.html  # the visual demo, for the owner's eyes — no agent in the main session reads it
```

Everything is written in English (owner, 02/11/2026). The owner's own words are quoted as said.
UI text, QA scripts for other people and client deliverables stay in the reader's language.

**Who reads what:** each session reads only `spec.md` + `plan.md` + `state.md` (~10k tokens),
plus `intent.md`/`facts.md` while writing the spec. **Never open `proposal.html` in the main
session** — it is 100–150 KB. A Sonnet subagent builds it from `spec.md`.

### intent.md (≤ 1 page)

```markdown
# Intent: <title>
Author: <owner> · Status: draft | approved <dd/mm/yyyy>
ID: <id> · Branch: feature/<id>-<slug> · Track: feature

## Problem
<what hurts today, with the owner's words quoted and a number when there is one>
## Proposed outcome
<what is true when this is done — no solution>
## Affected users and systems
## Constraints
<limits that hold no matter the solution: privacy, no horizontal scroll, production data…>
## Out of scope
## Open questions
```

### facts.md (≤ 2 pages)

How it works today, what the documents and code show, other projects that did something similar.
Every fact cites its source (`path:line`, document name). A list of what could not be read.
Never personal data: field names, never values.

### spec.md (2–4 pages) — the single source of truth

```markdown
# Spec: <title>
ID: <id> · Status: decisions open (n) | closed <dd/mm/yyyy>
Proposal: specs/<id>-<slug>/proposal.html (vN | approved dd/mm/yyyy) [· published: <artifact link>]   # only when there is a screen; the link only if the owner asked

## Requirements
R1 <one sentence>. (D1, D4)
## Acceptance criteria
A1 (R3) Given <state>, when <action>, then <observable result>. → Pest | Vitest | Playwright
A2 (constraint) At 390, 1024, 1280 and 1920px … no horizontal scroll. → Playwright
## Decisions
D1 <question> → <choice>. Why: <one line>. Rejected: <option> (<cost accepted>).
D11 <question> → C. ~~A~~ reopened in the grill: <why>.
## Constraints, met by
<each intent constraint → the R#/A# that guarantees it>
## Screens (when there is a proposal)
<screen name> — demo: <section/tab of the page> · app: <route> · widths: 1280, 390
## Out of scope
```

- IDs never renumber. A reopened decision is fixed **in place**, the old choice struck through.
- Decisions found while building are added as `C1, C2…` (revisable) with the reason.
- `plans/decisoes/` keeps only decisions that outlive a feature (architecture, hosting).

### plan.md (1–2 pages)

```markdown
# Plan: <title>
Approved: <owner>, <dd/mm/yyyy>
F1 <phase name> — files: … · tests first: … · proves: A1, A2 · done when: <check>
F2 …
F5 <app phase> — … · looks like: <the proposal's visible details for this screen: labels, hints,
   placeholders, field order, button states> · …
Every phase: failing tests → code → green → phase gate → state.md → one commit (code and state).

## QA
Env: local — API <url> · SPA <url>        # never production data
Logins: <role> → <username in qa.local.md> · …
Data: A13, A15 need <a published week, two namesakes> → <Feature>QaSeeder in F3 | demo data covers every A#
Human round: yes — <tester>, merge waits | after merge   # or: no (<why>)
```

Bottom-up order (api: migrations → DTOs → repositories → services → requests/controllers/resources;
app: types → services → stores → composables → atoms → molecules → organisms → views/routes).
Each phase fits one session.

**The `## QA` block** (owner, 10/11/2026) is everything the QA rounds need, decided while the
questions are cheap. **Data:** an A# whose "Given" state the demo seeders do not create gets a
`<Feature>QaSeeder` task in the phase that builds that behaviour, test first like any code; it
refuses to run outside local/testing and stays out of `seeders.local.php`. **Human round:** yes by
default, and whether the merge waits for it, per feature. In boanerges-3 none of this was planned:
the Chrome round could not run A13–A15, and the seeder and the human script were written after the
merge (US$ 4.70, the seeder reaching `main` with no CI). **The Chrome round runs on that same data**
when the human round is yes: a local database built only by the seeders, never a production pull.
boanerges-4 Release 2 ran it on a production copy, so a workbook in final review, a published week
and unsent S-89 cards, which the person's seeders-only database lacks, and five labels that read
differently there surfaced only while the human doc was being written (12/11/2026, PR #60).

**`looks like:`** comes from the frozen proposal, read by a Sonnet subagent at plan time (the main
session still never opens it). In boanerges-3 five such details (a tab hint, a search box, a
neutral "Salvar", a field's place, a placeholder) were found only by verify's demo comparison.

**Phase gate** (owner, 09/11/2026): a phase runs `/battoni-pre-commit` without the full app
Playwright suite. Every static check, the full Pest suite and the full Vitest suite run; of the
e2e, the phase runs only the spec files it adds or edits (`rtk make e2e E2E_ARGS=<spec file>`),
red then green. The full Playwright suite runs once, at `/battoni-verify`, before the PR: nothing
reaches the PR without every suite green.
`pre-commit green` on a phase's Done line means this gate. An ordinary commit outside a feature
build still runs the full `/battoni-pre-commit`.
*Retired:* "every phase runs `/battoni-pre-commit` in full". It ran the whole browser suite (170
tests, ~3 min) in every phase, phases that touched only the API included. The trial in boanerges-3
(F4–F9) saved ~2¾ min per phase and verify found no e2e break to trace back to a phase
(`plans/decisoes/2026-11-09-e2e-once-per-feature.md`).

### state.md (≤ half a page)

```markdown
# State: <title>
Stage: intent | spec | plan | build F<n>/<total> | verify | delivered — PR #<n>
Updated: <dd/mm/yyyy>
Branch: feature/<id>-<slug> · Worktree: .claude/worktrees/<id>
## Done
F1 … · F2 … · <suite counts: 963 Pest · 1140 Vitest> · pre-commit green <dd/mm>
## Next
<the exact next step, and the command>
## Pitfalls
- <non-obvious thing the next session will trip on> (C4)
## QA
<one line per round: date, layer, link, passed/problems, IDs opened>
Cost: US$ <x> (main <y>, subagents <z>) · <date>      # written by /battoni-verify
## Away run                                  # only in the away mode, written by /battoni-run
Stop at: PR after verify · Reviewer: code-reviewer · Chrome QA: on
Started: <yyyy-mm-dd hh:mm> · Ended: <yyyy-mm-dd hh:mm> — done | blocked at F<n> | stopped at F<n>
<one line per event: time, F<n> started/failed (coordinator) or done/blocked (builder), attempt, SHA>
## Decided while you were away              # only in the away mode
- W1 F2: <the question> → <the option taken> (<why>; to reverse: <how>)
```

Rewritten (not appended) at the end of every session, except the `## Away run` block and the
`## Decided while you were away` list, which every rewrite keeps and only adds to. It replaces `/battoni-agent-handoff` for
feature work.

### proposal.html — settled decisions and the working demo

Built by the `/battoni-new-proposal` skill (Sonnet author) from `spec.md`. Two stages, no
decision to make on the page and no animation (owner, 04/11/2026):

- **early** — only when the owner wants to see an open decision before answering it (the
  `/battoni-spec` grill offers "Show me first"). The demo as the spec stands, with a plain
  **variant switcher** per open D#. The answer still comes through `AskUserQuestion` in chat.
- **final** — after the grill, every D# closed: header with "Approved on <date>", a **Settled
  decisions** section (a card per product D#: the choice and one line of why), then the **full
  working demo** with the chosen options. **Frozen** from then on; the Chrome QA compares the
  built app against it.

**Local first (03/11/2026).** The page is a file in the feature folder, committed with the spec;
the owner opens the absolute path. It becomes an Artifact only when the owner asks ("publish the
proposal", `/battoni-new-proposal <id> publish`); the link is then added to the `Proposal:` line.

*Retired 04/11/2026: decision cards with a copied choices line (`<id> D3=b · obs …`), versions
v1/v2/v3 and animated clips.* The owner answered in chat anyway, and building the cards and clips
was 58% of the spec stage of boanerges-1 (US$ 3.29 of 5.68).

## Worktrees — one per piece of work (boanerges-7)

The main checkout stays on `main`: no session switches its branch or commits there (on 10/11/2026
a branch switch there hijacked another session). The Bash guard enforces it by the hook's `cwd`,
and inside a worktree it blocks a switch to any other branch. Reads stay in the main checkout;
every session that edits files works in `.claude/worktrees/<id>`. The owner's recovery when the
main checkout is already off `main` is `! git switch main` (an owner `!` skips the hook).

| Step | Command (rtk form) | What it does |
| --- | --- | --- |
| create | `rtk make wt-new ID=<id> [SLUG=<slug>] [BRANCH=<branch>]` | `git fetch`, the worktree on `feature/<id>-<slug>` (`feature/<id>` without SLUG) from `origin/main` with no upstream, so a bare push never targets `main`; BRANCH = an existing local branch as it is, one only on `origin` (tracked), or a new name from `origin/main` (`fix/<id>-<slug>`). Clones `vendor/`/`node_modules/` (`cp -c`), reconciles them (`npm` under the unit's `.nvmrc` Node via `fnm exec`, also for `wt-app`; warns if an install leaves the tree dirty), writes `.worktree.json`. Refuses a label or a `<project>_<label>` DB another worktree holds. |
| enter | `EnterWorktree` with `path: .claude/worktrees/<id>` | moves the session in; `/battoni-intent` and `/battoni-start-session <id>` do it |
| serve | not in rnc | `wt-up` (inherited from boanerges) serves a worktree through Valet and copies a MySQL database. rnc runs on `php artisan serve` and SQLite, so `wt-up` refuses naming SQLite before touching anything, and `wt-app` refuses for want of the record `wt-up` writes. A browser round in a worktree: `make fresh-seed` (its own `database/database.sqlite`, gitignored), `make serve-api` and `make serve-app` from the worktree, with the main stack stopped, since both targets take the fixed ports 8000 and 5173. |
| sweep | `rtk make wt-gc` | at `/battoni-start-session` and `/battoni-close-session`: `wt-down` on every worktree that passes, the rest `kept` (failed check) or `left behind` (PR closed unmerged, or no commit and no session for 7 days), never torn down on its own; a left-behind line names its `wt-discard` dry run |
| tear down | `rtk make wt-down ID=<id>` | only when no other worktree holds its label (in a MySQL project its DB name would be the other one's), its PR is merged, nothing is ahead of `origin/main`, `git status` is clean, every ignored file is regenerable, no session in it for 30 min and its Vite is quiet; then (in a Valet/MySQL project it first undoes what Valet shows `wt-up` left and drops the DB; rnc has neither) it removes the folder and deletes the branch (`-D`, since GitHub's merge commit is not in the local `main`). A failed check names itself and touches nothing. |
| discard | `rtk make wt-discard ID=<id>`, then `rtk make wt-discard ID=<id> CONFIRM=<id>` | throws an UNMERGED worktree away (a closed PR, abandoned work): every `wt-down` check except "PR merged" and "nothing ahead of `origin/main`", and refuses an OPEN PR (`close PR #<n> first`). Without `CONFIRM` repeating the ID it is a dry run: the checks, then the folder and branch (and in a Valet/MySQL project the Valet steps and DB) with its tip SHA it would remove, and the confirmed command. Confirmed, it prints `git branch <branch> <sha>` (the only way back) before `wt-down`'s same teardown. No prompt, so owner and agent run the same command; the remote is never touched. `/battoni-close-session` offers it per left-behind worktree. |
| e2e | `rtk make e2e [E2E_ARGS=<spec file>]` | Playwright on this checkout's own e2e port (`python3 scripts/worktree.py port`: a worktree's record, or the main checkout's `base + 100`, claiming the block on first use; an exported `E2E_PORT` wins). Playwright always starts its own `vite --mode test` there and never reuses a server, so a taken port fails before the first test; a local run without `E2E_PORT` fails naming `make e2e`. The run always exits (html report `open: 'never'`, `npx playwright show-report`), and a test that passes only on its retry fails it. |

Ports: each project claims a block of 200 from 6000 in `~/.config/battoni/port-blocks.json` (per
machine, never in git); feature `<n>` gets Vite `base + n` and e2e `base + 100 + n` (the main
checkout's e2e is `base + 100`), anything else the first free slot from 80 to 99. The scripts
refuse unless the main checkout's api `.env` is local (`APP_ENV=local`, `DB_HOST`
127.0.0.1/localhost, source DB = the project, target `<project>_<label>`): production is never read or written.

**What is not ported to rnc.** `wt-up` and `wt-app` (Valet sites, a MySQL copy of the local DB, sessions
in the main database's table, and the one-time `valet trust` that goes with them) exist in
`scripts/worktree.py` because the script is synced as it is, but rnc never runs them: it has no Valet
and no MySQL in development. `new`, `down`, `discard`, `gc` and `port` do work: with
`DB_CONNECTION=sqlite` in the main api `.env` they skip every MySQL step (`SqliteProjectTest`). The
Ports paragraph's "api `.env` is local" check applies only to a MySQL project. If rnc one day serves
through Valet, drop the SQLite branch of the script, not the other way round.

## Sessions

| # | Command | Reads | Writes | Owner |
| --- | --- | --- | --- | --- |
| 1 | `/battoni-intent <idea>` | nothing of the feature | `intent.md`, `facts.md` (Sonnet) | picks the answers (rule 3), approves one page |
| 2 | `/battoni-spec <id>` | `intent.md`, `facts.md` | `spec.md`; `proposal.html` via Sonnet if there is a screen | picks on the page, answers the grill |
| 3 | `/battoni-plan <id>` | `intent.md`, `spec.md` | `plan.md` (with `## QA`), initial `state.md` | approves the plan, answers the QA block |
| 4…n | `/battoni-start-session <id>` (once per phase) | `spec.md`, `plan.md`, `state.md` | tests, code, `state.md`, one commit | nothing, unless asked |
| last | `/battoni-verify <id>` | `intent.md`, `spec.md`, `plan.md` → `## QA`, `state.md`, the branch diff | findings, `qa-script.md` (Sonnet), Chrome rounds, the human QA doc when the plan says yes, cost line, PR | reads QA and PR; shares the human doc |

`/clear` between every row in the attended mode. The away mode runs rows 4…last with nobody
there (next section).

## Two modes — attended and away

The owner is needed for **decisions**, not for building. Both modes write the same files; they
differ in who moves from one row to the next.

- **Attended** — the table above, one row per session, the owner starting each one. For a feature
  with open product decisions or a screen the owner wants to see before it is built.
- **Away** — one sitting for the decisions, then nobody. `/battoni-intent <idea> away` runs rows
  1–3 in the same session (no `/clear` between them; each still commits its file, and if the
  context passes ~150k, stop and hand off to `/battoni-spec <id> away` or `/battoni-plan <id> away`
  after a `/clear`). The plan step then grills every question a build would stop on, so it is
  answered before the owner leaves, and writes the `## Away run` block. The sitting ends with
  `/loop /battoni-run <id>` (Claude Code only; the `/loop` gives it its heartbeat): a coordinator that builds every phase in a fresh subagent, verifies, and
  stops at a draft PR. The owner comes back to `state.md` and the PR.

A one-prompt away run, where the spec is written from recommended answers alone, is not offered: a
wrong guess in the spec is hours of code built on it, and the sitting is what makes leaving safe.

### Decided while you were away

In the away mode nobody answers, so a question the build meets takes its recommended option, the
one an attended session would have marked `(recommended)`, and is logged in `state.md` as `W#`:
the question, the option taken, why, and how to reverse it. A `W#` that changes behaviour the spec
describes is also a `C#` in `spec.md`. The PR copies the `W#` list, so the owner reviews each
decision before merging.

**Hard stops — never decided away.** The phase stops as **blocked**, with the reason in
`state.md` → Next, when the only way on is:

- irreversible or outward: a migration on a database that is not local, a deploy, a push to
  `main`, a force push, a merge, publishing an artifact, sending a message;
- against the spec: the approved `R#`/`D#` cannot hold as written, and going on means redesigning
  it rather than taking an option inside it;
- a tool call the permissions blocked: never retried another way;
- the same phase failing twice.

The run's own push of the feature branch and its draft PR are planned steps, not decisions taken
away. So is the human QA doc when the plan's `## QA` says yes: the run creates it private in the
owner's Claude Docs and never shares it; sharing it with the tester is the owner's step.

A later phase never starts on top of a blocked one.

## After delivery

A bug in a delivered feature follows the small track, with its own ID, and cites the `R#` it broke.
If the fix changes a rule, `spec.md` changes with it, so the spec still tells the truth months later.

**New scope after verify** (owner, 10/11/2026). While the PR is open and the new requirement serves
the same intent, amend in place: the spec header gains `Amended: <dd/mm/yyyy> — <what>`, new
`R#`/`A#`/`D#` continue the numbering, the plan gains phases, and those phases go through verify
again (review of the amendment, its QA rounds). Once the PR is merged, new scope is a new feature
ID. boanerges-3 did the first (F10–F12, R11–R14) with no rule to say so.

**Nothing reaches `main` outside a PR** (owner, 10/11/2026). Before the merge, the feature worktree
is clean (`git status --porcelain` empty, untracked files included): `/battoni-verify` checks it
before marking the PR delivered, and the owner merges only then. Code written after the merge,
even a QA helper, takes the small track: a branch and a PR, so CI runs first. In boanerges-3 the
QA seeder sat uncommitted when PR #41 merged and went to `main` directly (`c8c45ba`).

## The index — no file to edit

Every feature's `state.md` carries a `Stage:` line, so the index is a command, not a table that
every branch edits (two open PRs would conflict on it):

```bash
grep -H '^Stage:' specs/*/state.md                       # on this branch
for b in $(git branch --format='%(refname:short)' | grep -E '^(feature|fix)/'); do
  git show "$b:specs/${b#*/}/state.md" 2>/dev/null | grep -m1 '^Stage:' | sed "s|^|$b  |"; done
```

Who sets the stage: `/battoni-intent` (intent), `/battoni-spec` (spec), `/battoni-plan` (plan),
`/battoni-start-session <id>` (build F<n>/<total>), `/battoni-verify` (verify, and as the branch's
last commit `delivered — PR #<n>`, which is true once the PR merges: a folder on `main` is delivered).
Small-track work has no folder: its trace is the branch, the commits and the PR.
