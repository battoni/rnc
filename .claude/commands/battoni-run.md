# Run

The away mode of the feature flow: build every remaining phase, then verify, with nobody at the
keyboard, and stop at a draft PR. The owner decided everything in the sitting that wrote the spec
and the plan; this session only carries it out.

**Usage:** `/loop /battoni-run <id>` — the `/loop` is what makes `ScheduleWakeup`, the heartbeat,
available; a bare `/battoni-run <id>` has no fallback if the connection drops.

Claude Code only (subagents and `ScheduleWakeup`); in Cursor, use the attended mode.

Reference: `.claude/feature-flow.md`, section "Two modes". Crash survival comes from the
`midnight-oil` skill (load it); this command is how that skill runs a feature folder.

## Why a coordinator

A wakeup re-invokes the same session, so a run that builds inside it re-reads a context that grows
all night: every phase's files, tests and fixes, paid again on every turn. Here the session is only
a **coordinator**. Each phase is built by a fresh subagent and comes back as a ten-line report; the
coordinator never opens product code. Its memory is `state.md`, the same file `/clear` relies on in
the attended mode.

## Step 0 — Preflight (the owner is still here)

**Resume first.** If `state.md` → `## Away run` already has a `Started:` line, this is a resume:
skip Step 0. If the log's last `F<n> started` line has no `done`, `failed` or `blocked` line
after it and that phase's agent has not reported back, a builder is still working: re-arm the wakeup and spawn
nothing. Otherwise go to the first phase whose `<id>: F<n>` commit is not on the branch
(`git log --oneline main..`), or to Step 2 when every phase has one, even at `Stage: verify`.

1. `state.md` says `Stage: plan` or `Stage: build F<n>/<total>`, and `plan.md` has an `Approved:`
   line. Otherwise stop: the decisions are not taken yet, run the attended rows first.
2. `state.md` has an `## Away run` block (`/battoni-plan <id> away` writes it). If not, ask for
   it now, one `AskUserQuestion` round:
   - **Stop at** — draft PR after verify (recommended) · after the last phase, no verify · after F<n>.
   - **Reviewer** — `code-reviewer` on Sonnet (recommended) · `fable-reviewer`, offered only when
     this session's prompt contains `#fable` (the owner's rule: a Fable subagent only as a
     reviewer in a `#fable` session). Without `#fable` there is no question: the reviewer is
     `code-reviewer` on Sonnet.
   - **Chrome QA** — run it (recommended, needs Chrome open with the extension connected and the
     API and SPA served locally) · skip it and list every Chrome round as owed in the PR.
3. Be in the feature's worktree: `EnterWorktree` with `path: .claude/worktrees/<id>` (none →
   `rtk make wt-new ID=<id> BRANCH=feature/<id>-<slug>` first), never a checkout in the main
   checkout, which stays on `main`; the tree must be clean. Chrome QA on → `make fresh-seed`, `make serve-api` and `make serve-app` from there (no `wt-up` in rnc).
4. If Chrome QA is on: `curl` the API and the SPA, and open one tab through the
   `battoni-chrome-driver` agent. A failure here is said now, not found at 3 a.m.
5. Tell the owner, in one block, what must already be allowed for nobody to be asked: `git add`,
   `git commit` and `git push` of this branch, `gh pr create`, everything `/battoni-pre-commit`
   calls (`make`, `npm run`, `npx`, `php artisan test`, `vendor/bin/*`, `composer`), `curl`, and
   the Chrome extension's tools when Chrome QA is on. A blocked tool call stops the phase as
   **blocked**, never retried another way. Also suggest `caffeinate -i` in another terminal so the
   Mac does not sleep.
6. Write `Started: <date time>` in the `## Away run` block, commit `state.md`
   (`<id>: Start away run`), and say "you can leave".

## Step 1 — One phase per subagent

For the next phase without its commit, first add `F<n> started, attempt <a>` to the
`## Away run` log, **without committing**: a wakeup reads the file on disk, so it already knows a
builder is at work, and the builder's one phase commit carries the line. Who writes the log: the coordinator writes `started` and `failed`, the builder writes
`done` or `blocked` for its own phase. Then spawn one `general-purpose` agent with `model: "opus"`
(building is judgment work, `.claude/delegation-policy.md`). The brief, and nothing else from this
conversation:

> Build phase F<n> of feature `<id>` (attempt <a>). Follow
> `.claude/commands/battoni-start-session.md`, section "With a feature ID", steps 1–5, exactly:
> tests red first, then code, the phase gate (`/battoni-pre-commit` without the full Playwright suite;
> only the e2e files this phase adds or edits), then **one** commit with the code and `state.md`
> together, subject `<id>: F<n> …` (never a separate state commit). Run `/battoni-pre-commit` inline; a
> review it would hand to an agent you cannot spawn, you do yourself. You are in **away mode**
> (`.claude/feature-flow.md`, "Decided while you were away"): never ask; a question takes its
> recommended option and is logged as `W#`, and as a `C#` in `spec.md` when it changes behaviour
> the spec describes. When you rewrite `state.md`, keep the `## Away run` block and the
> `## Decided while you were away` list, adding to them: one log line for this phase
> (`F<n> done` or `F<n> blocked: <reason>`), your `W#`.
> Stop the phase as **blocked** only for a hard stop from that section, and write why in
> `state.md` → Next. Never merge, never push to `main`. Return at most ten lines: phase, commit
> SHA, suite counts, `W#` taken, `blocked: <reason>` or `done`.

Wait for its completion notification, then read only `state.md` → `## Done`, `## Next` and
`## Away run`.

- **done** → the next phase.
- **failed** (no commit, or red suites) → add `F<n> failed, attempt <a>` to the `## Away run` log,
  commit `state.md` at once, and retry with attempt <a+1>. The log is the counter, so a resume
  keeps it.
- **blocked**, or the second failure → stop the build. Never start a later phase on top of a phase
  that is not done; go to Step 3.

## Step 2 — Verify

When every phase is done and the stop condition includes verify, follow `/battoni-verify <id>` in
this session, in its away mode, keeping it a coordinator (its layer 1 is the feature's one run of
the full app Playwright suite): the review goes to the reviewer named in
`## Away run`, each fix to a fresh `general-purpose` `opus` agent briefed with the finding (red
test first, then the fix, `/battoni-pre-commit`, commit, the same `state.md` rule as Step 1),
`qa-script.md` and the human QA doc (when `plan.md` → `## QA` says yes) to their Sonnet agents,
and each Chrome round to `battoni-chrome-driver`. The PR is opened through `/battoni-publish-pr`
(which pushes and opens a **draft**) with the body `/battoni-create-pr` writes, plus a
**Decided while you were away** section that copies the `W#` list.

## Step 3 — Stop

Rewrite `state.md` as the morning report, keeping every block: `## Done` with suite counts and
the PR link, `## Next` with the first thing the owner must do (read the `W#` list, unblock F<n>,
run an owed Chrome round), and the `## Away run` block closed with
`Ended: <date time> — <done | blocked at F<n> | stopped at F<n>>`. `Stage:` stays what verify set
(`delivered — PR #<n>`) or the build stage it stopped at. Commit it, push the branch, and end the
heartbeat: `ScheduleWakeup` with `stop: true`.

## Heartbeat

The subagent's completion is what wakes the coordinator; the wakeup is only the fallback. End
every coordinator turn that waits on one with a `ScheduleWakeup` of 1800 seconds and the prompt
`/loop /battoni-run <id>`, which resumes through "Resume first" above, so a dropped connection does
not end the night. A wakeup that finds a builder still at work (a `started` line with no `done`,
`failed` or `blocked` after it) only re-arms: two builders on one tree is the failure this guards.

## Never

- Merge, deploy, push to `main`, force-push, or publish an artifact. Pushing the feature branch
  and opening its draft PR are the run's planned steps, not one of these.
- Ask the owner: there is nobody to answer, and an `AskUserQuestion` waits until morning.
- Open `proposal.html` or product code in the coordinator.
- Spawn a Fable agent unless this session's prompt contains `#fable` and `## Away run` says
  `fable-reviewer`.
