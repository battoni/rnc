---
name: midnight-oil
description: "Run a multi-phase implementation plan autonomously and unattended (overnight), building → reviewing → testing in Chrome → committing phase by phase, and surviving a mid-run API/connection crash so the loop never silently stalls. Load when asked to 'build these phases autonomously', run an overnight/unattended build, burn the midnight oil, or keep a phased build going crash-resiliently without a human babysitting it. Pairs with the project phase loop (`cortex/plans/phases/README.md`) and /battoni-pre-commit, /battoni-commit, /battoni-publish-pr."
license: MIT
metadata:
  author: rnc
---

# Midnight Oil — Autonomous, Crash-Resilient Phased Build Loop

> **Scope:** an operator hands you a **master plan** + an ordered **list of phases** and says
> "build these overnight, test as you go, and don't stall if the connection drops." You run a
> tight build → review → test-in-Chrome → fix → commit loop, one phase at a time, re-arming a
> wakeup at the end of **every** turn so a crashed turn can never freeze the whole run.
>
> This skill does **not** replace the repo's phase discipline or git commands — it **drives**
> them. Reuse, do not reinvent:
> - Phase loop & tracker: `cortex/plans/phases/README.md` (spec → build → QA → merge → next).
> - Ship commands: `/battoni-pre-commit`, `/battoni-commit`, `/battoni-publish-pr`.
> - Convention gates those commands invoke (`/battoni-review-vue-conventions`,
>   `/battoni-review-design-conventions`, `/battoni-review-arcus-code`), where the repo has
>   them. A repo without an app or an API has none; its review is `code-reviewer` on the diff.

## When to load this skill

Load when the operator wants an **unattended, multi-phase** build to run to completion without a
human present — especially when a dropped API connection ("API Error: Connection closed
mid-response") would otherwise kill a turn and leave the run dead until someone returns. If it's a
single interactive change, you don't need this — just build it.

**Start it under `/loop`** (`/loop <the run prompt>`, or `/loop /battoni-run <id>` for a feature
folder). `ScheduleWakeup`, the heartbeat below, is available only in `/loop`'s dynamic mode; a run
started without it has no wakeup to arm, and the first dropped connection ends the night.

---

## Inputs (what the operator gives you)

1. **Master plan** — the authoritative spec/roadmap the whole run must stay true to
   (e.g. `plans/<project>-roadmap.md` or a linked doc). Every review reconciles against this.
2. **Phase list** — the ordered ids to build this run (e.g. `A2, A3, A4`), each with (or needing)
   a phase spec under `cortex/plans/phases/<id>-*.md`. Only the **current** phase spec must be final; a
   later phase's spec is (re)written just-in-time, informed by what actually merged (step 6).
3. **Stop condition** — normally "all listed phases done". The operator may also cap it
   ("stop after A3", "stop at 06:00", "stop if the same phase fails twice").

If the master plan or the current phase spec is missing, **write/normalize it first** (following
`cortex/plans/phases/README.md`) before building — a resume must never guess intent.

---

## The loop (per phase)

Run this for the current phase, then advance to the next, repeating until the stop condition.

1. **FIRST, arm the safety net.** Before any build work, confirm a wakeup is pending (see
   **Crash survival** — this is the entire point of the skill). On a fresh invocation the very
   first thing you do is read the progress file and confirm/plan the next chunk; you end this turn,
   like every turn, by re-arming the wakeup.
2. **Build** the current chunk of the phase spec. One small, self-contained chunk per turn — not
   the whole phase (see **Keep turns short**).
3. **Review what was built** against **both** the phase spec **and** the master plan. Does it match
   the phase's acceptance criteria? Does it still serve the master plan's intent? Note drift.
4. **Test in Chrome** — drive the running app in the browser (the real, running frontend) for
   anything user-facing, **plus** the normal automated tests where applicable
   (`api.rnc` Pest, `app.rnc` unit and e2e, or whatever the repo's `make pre-commit` runs where
   it has neither). "Green in the terminal" is not
   "works in the app" — do both.
5. **If any issue: fix → review again (step 3) → test in Chrome again (step 4).** Loop until the
   phase is **genuinely** clean — no known regressions, spec + master-plan satisfied. Do not
   advance on a "probably fine".
6. **Reconcile the next phase's spec** against what was just built + the master plan. Rewrite/update
   `cortex/plans/phases/<next-id>-*.md` so it's informed by reality (this is exactly the repo's
   "only the next spec is written at a time, freshest, informed by what just merged" rule in
   `cortex/plans/phases/README.md`). Record decisions in the progress file.
7. **Ship:** run `/battoni-pre-commit` (mechanical gate + convention reviews) → `/battoni-commit`
   (grouped conventional commits) → `/battoni-publish-pr` (push + draft PR / update existing).
   Do **not** hand-roll these steps — invoke the commands. Honor their rules (no AI-authorship
   trailer, never `git add -A`, never push to `main`, PR base is `development`).

Then update the progress file (phase done, next phase = current) and continue at step 2 for the
next phase. When the last listed phase ships, **stop cleanly** (see **Ending the run**).

**Chunk boundaries.** A "chunk" is the largest unit you can build + review + test inside one short
turn. Prefer finishing a review/test chunk and re-arming over cramming a big build into one turn —
a big turn is a big crash window.

---

## Crash survival (read this carefully — it is the whole point)

**The failure this skill exists to prevent:** an API/connection error kills a turn mid-response. If
nothing is scheduled to re-invoke the session, the autonomous run **stalls silently** until a human
comes back. Overnight, that wastes the whole night.

**The mechanism: `ScheduleWakeup`.** It schedules this same session to be re-invoked after a delay,
with a prompt you supply. Its critical property:

> **`ScheduleWakeup` ends / yields the turn.** It must be the **LAST** action of a turn. You
> cannot "arm it and keep working" in the same turn — arming it *is* ending the turn. So you arm
> it once, at the very end, every time.

Because of that constraint, the resilient pattern is **short turns + re-arm at the end of every
one**:

1. **Keep every turn SHORT.** One small build/review/test chunk per turn. The dangerous window is
   *mid-turn, before the end-of-turn re-arm* — the only stretch where no wakeup is pending. Short
   turns shrink that window to near-nothing.
2. **End EVERY turn with `ScheduleWakeup`** — short delay (**~120s**), with a prompt that
   **re-invokes this same `midnight-oil` loop and points at the progress file**, e.g.
   *"Resume the midnight-oil run. Read `plans/<run>-progress.md`, pick up at the NEXT step, and
   continue the loop."* This is the heartbeat: even after a clean turn, the next wakeup is already
   pending, so a subsequent crash still gets re-invoked.
3. **Maintain a progress file** (see contract below), updated **after every chunk**, so **any**
   resume — normal wakeup or post-crash — reconstructs full state from the file with zero reliance
   on in-context memory. Treat the progress file as the single source of truth for "where am I".
4. **Re-arm defensively.** If a turn is going to do risky/long work, still keep it small; the
   invariant is "a wakeup is always pending for after this turn". Never let a turn end *without*
   either a re-armed wakeup or an explicit stop.

### Residual gap the heartbeat can't fully close

There is one window `ScheduleWakeup` alone cannot cover: if a turn **crashes mid-response before it
reaches its end-of-turn `ScheduleWakeup`**, and that crashed turn was the one meant to arm the next
wakeup, then **no wakeup is pending** and the chain is broken. Short turns make this window tiny but
not zero. This is why a decoupled, external watchdog is worth considering.

### `ScheduleWakeup` vs. an external cron watchdog (belt-and-suspenders)

- **`ScheduleWakeup` (primary heartbeat)** — armed *by the session itself* at end of turn,
  re-invokes *the same session* (full context/state preserved). Ideal for the normal cadence.
  **Weakness:** it's coupled to the turn lifecycle — a crash before the re-arm leaves nothing
  pending (the residual gap above).
- **External cron watchdog (backstop)** — fires on a **wall-clock schedule, independent of the
  session**. Because it does not depend on the crashed turn completing anything, it **survives the
  mid-turn-before-rearm crash** that `ScheduleWakeup` can't. Each fire reads the progress file and
  resumes the loop. This is genuinely more crash-resilient **as a liveness net** — and is the
  recommended belt-and-suspenders when the environment supports it.

**Recommended posture:** keep `ScheduleWakeup` as the **primary heartbeat** (short turns, re-arm
every turn). Add an external cron **only as a coarse backstop** that re-kicks the run if the
heartbeat chain ever breaks — it does not replace the heartbeat.

**Accuracy caveats — verify in your environment before relying on a cron; do not assume:**

- A tool literally named `CronCreate` may or may not be present in the operator's harness. If it is,
  read its own description for exact params before using it. **Do not invent its behavior.**
- The cron facility this repo *does* have documented is the **`schedule` skill's cloud routines**
  (`RemoteTrigger`) — an isolated **cloud** session on a cron. Two real limits make it a *coarse*
  backstop, not a fast one:
  - **Minimum interval is 1 hour** — so "watchdog every few minutes" is **not** achievable via
    cloud routines; recovery latency is up to ~1 hour.
  - It spawns a **fresh, isolated cloud** session with **zero local context** and **no access to
    the operator's local machine** — so it can resume progress-file-driven build/review/commit
    work, but it **may not reach a locally-running app/dev server for the Chrome test step**. A
    cloud-resumed chunk may have to defer Chrome testing to a step that can run in that environment,
    or flag it in the progress file for the next local resume.
- **If neither a short-interval local cron nor an acceptable cloud backstop is available, do NOT
  fake one.** Fall back to the safe pattern that always works: **short turns + end-of-every-turn
  `ScheduleWakeup`**, and tell the operator the residual mid-turn-crash gap exists so they can set
  an external monitor if they want full coverage. Prefer the safe, honest pattern over a
  cron you're not certain about.

---

## Progress file contract

Keep one durable progress file per run, e.g. **`plans/<run>-progress.md`** (pick a stable name at
run start and never move it — a resume must find it). Update it **after every chunk**, before you
end the turn. It must let a cold resume continue with zero context loss. 
**Feature flow (`.claude/feature-flow.md`):** when the plan is a feature folder
`specs/<id>-*/`, the progress file **is** that feature's `state.md`, the phases are the
`F#` of its `plan.md`, and each phase is one `/battoni-start-session <id>` cycle. Add the run header
and the stop condition at the top of `state.md`; never open `proposal.html`. **Drive a feature folder
through `/battoni-run <id>`**, not this loop directly: a wakeup re-invokes the same session, so a
night built inside it re-reads every phase's files on every turn. `/battoni-run` keeps this
session a coordinator and builds each phase in a fresh subagent; this skill supplies its heartbeat.
Otherwise, include:

- **Run header** — run id/name, start time, the **stop condition**.
- **Master-plan pointer** — path to the authoritative master plan.
- **Phase list & status** — every listed phase with `todo / in-progress / done` and its PR link
  once shipped (mirror the `cortex/plans/phases/README.md` legend where it makes sense).
- **Current phase** — which id is active and a pointer to its spec.
- **NEXT step (exact)** — the single most important field: precisely what to do on resume
  ("build chunk X", "re-run Chrome test on Y", "run /battoni-pre-commit"). A resume executes this.
- **Decisions & reconciliations** — spec/master-plan drift found and how it was resolved
  (feeds step 6 for later phases).
- **Open issues / retries** — anything failing, and the retry count (for a "stop if it fails twice"
  cap).
- **Log** — append-only, timestamped, one line per chunk (build/review/test/fix/commit), so the
  trail survives crashes and is auditable in the morning.

---

## Ending the run

- **Clean completion (all listed phases shipped):** stop the heartbeat by calling
  `ScheduleWakeup` with **`stop: true`** (no further wakeups). If you armed an external cron
  backstop, disable/remove it too so it doesn't re-kick a finished run. Write a final summary to the
  progress file: what shipped, PR links, anything deferred, and any Chrome tests a cloud resume
  couldn't run locally.
- **Bounded stop (operator cap hit — time limit, phase cap, repeated failure):** stop the same way
  (`stop: true` + disable the cron), and leave the progress file's **NEXT step** pointing at exactly
  where a human should pick up, plus why it stopped.
- **Never** end a run by simply going quiet. Either a wakeup is pending (continue) or you have
  explicitly stopped (`stop: true`) and said so in the progress file. Silence is the failure mode
  this skill exists to eliminate.

---

## Guardrails

- **One phase's PR per concern** — let `/battoni-commit` and `/battoni-publish-pr` group and base
  correctly (`development` base, draft PR). Never push to `main`/`master`.
- **Never `git add -A`** — stage by explicit path (parallel-session safety; see the `cortex-sync`
  skill). Multiple sessions may share the tree overnight.
- **No AI-authorship trailer** on commits (repo convention — enforced by `/battoni-commit`).
- **Don't skip the Chrome test** to save a turn. If a resume can't reach the local app (cloud
  backstop), record it as an owed test in the progress file — don't mark the phase clean.
- **Don't advance on red.** Step 5 loops until genuinely clean; a phase is done only when spec +
  master plan are satisfied and tests + Chrome pass.
- **Keep the skill honest about the tools.** If `ScheduleWakeup` / cron tool names or params differ
  in this environment, adapt to the real tool descriptions — the *pattern* (short turns, re-arm
  every turn, progress-file-driven resume, external backstop if available) is what matters.
