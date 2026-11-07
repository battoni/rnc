# Verify

> Last stage of the feature flow. A reviewer that never saw the build conversation checks the
> branch against the spec; QA runs in three layers, cheapest first; the PR maps every R# to the
> test that proves it.
>
> **Usage:** `/battoni-verify <id>`
> Reference: `.claude/feature-flow.md`. Reads `intent.md`, `spec.md`, `state.md` and the branch diff only —
> **never `proposal.html`** (the Chrome driver opens it, not you).

## Step 0 — Preconditions

- `state.md` says every phase is done, with `pre-commit green` on the Done line. Otherwise stop
  and say which phase is missing.
- Set `state.md` to `Stage: verify`.
- Note the suite counts from `state.md`; they go into the PR.

## Step 1 — Review against the spec (Fable finds, you check)

Run it **once**, on the whole branch, before any Chrome round: a finding fixed after QA means QA
runs again. Spawn the `fable-reviewer` agent (user level, `~/.claude/agents/`) with: the path of `spec.md`,
the base (`main`), the sub-projects touched and the rule files that apply to them
(by path — it reads only the ones named), picked by each sub-project's Type as in step 2 of
`.claude/agents/code-reviewer.md`: always `shared-conventions.md`, the `.claude/rules/` files for
an API or an app, and for anything else the row's own `CLAUDE.md` or `README.md` plus the
`RULES.md` chain of the changed files. It checks the spec **and** those
conventions: every R# implemented, every A# with a test that asserts it, nothing built that the
spec does not ask for. Do not send it the conversation or your opinion of the build.

Never a `general-purpose` agent on Fable: it starts at ~69k tokens of context against ~13k for
`fable-reviewer` (measured 2026-11-03; one such review cost US$ 3.62). Fable needs the owner's
permission (or `#fable` in the session; an away run uses it only when its session had `#fable`
and `## Away run` says `Reviewer: fable-reviewer`); without it, or when `fable-reviewer` is not installed,
spawn `code-reviewer` on Sonnet with the same inputs.

Check each finding in the file before accepting it. Real → fix (red test first when it is
behaviour), `/battoni-pre-commit`, commit. Rejected → one line with the reason; it goes into the PR.

## Step 2 — QA, three layers

Each layer starts only when the one before passed.

1. **Tests** — already green from the build (every A# has its test). Re-run the suite once on the
   final branch.
2. **Chrome driver** — load the `battoni-test-in-chrome` skill. The script comes from the A# list
   (not written by hand); an A# already proved at a width by Playwright gets no Chrome round at
   that width (cite the test in the Preflight). Every round signs in by opening the API's QA login
   link, `/api/qa/login-as/<username>?to=/<route>`, on the API host (local only —
   `QaLoginAsController`). The username comes from the project's QA notes (e.g. `qa.local.md`).
   A round never reuses the session of the round before. When the spec header says `(approved …)`, add the round
   **Compare with the approved demo** described in the skill, with the `## Screens` list of the spec. You judge only the failures and
   the differences.
3. **A person (a tester, the client)** — only when the owner asks. Load
   `battoni-new-qa-session`: one section per R#, each step citing its A#, then a free-use section.

Each layer's result is one line in `state.md` → `## QA`.

**Away mode** (`/battoni-run`): layer 3 never runs, it is listed as owed in the PR. With
`Chrome QA: off` in `## Away run`, layer 2 does not run either: `## QA` and the PR list every
round as owed. Nobody answers questions: see the last bullet below.

Problems found:
- **violates an A#** → a bug: a failing test, then the fix (in this branch while it is open; after
  merge, the small track with its own ID, citing the R#);
- **outside the spec** → either the script was wrong (fix the script) or it is a new wish: a new
  D# for the owner, or a new intent. Never fix silently. Away, it is a `W#` and a line in the PR
  for the owner, never built.

## Step 3 — PR

Load `/battoni-create-pr`. Title `<id>: <imperative subject>`. The body opens with the intent's
problem and outcome in two lines and a table **R# → test file::test name**, then the review
findings not applied with the reason, the QA lines, and the suite counts. As the branch's last
commit, set `state.md` to `Stage: delivered — PR #<n>` and Next to "merge": once merged, it is true.

End with: the PR link, layers passed, anything that could not be tested and why.
