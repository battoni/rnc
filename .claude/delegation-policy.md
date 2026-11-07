# Delegation policy — cheap models and isolated context

Shared reference for every command, skill and playbook that hands work to a subagent. A command's own
body names the step and the return contract; this file is the **why and the rules**, defined once.
Read it when a command says "delegate … per the delegation policy".

~99% of what a week costs is context being re-read: every byte that lands in the
main session is paid again on every later turn.

## The two savings (do not conflate them)

1. **Context isolation** — a subagent's bulky reads stay in ITS context and never re-enter the main
   session. This is the bigger lever, and it works with any model.
2. **Cheaper model** — a Haiku/Sonnet subagent burns cheaper tokens. Secondary, and only on what the
   subagent itself processes.

The biggest win is where both apply: the main model needs only a distilled result, and a cheap model
can produce it.

## Who does what

| Agent | Model | For |
| --- | --- | --- |
| `battoni-reader` | Haiku | Read-only mechanical fetch: git/gh reads, a tree walk, "where is X used", a log or JSON pull, a tool run. Returns only the asked-for result. Never writes. |
| `Explore` | pass `model: "sonnet"` | Broad searches that only need a location or a conclusion. |
| `battoni-light-author` | Sonnet | Light generative work from a brief the main model wrote (proposal pages, syncs). Escalates instead of judging. |
| `battoni-chrome-driver` | Sonnet | One round of a QA script in Chrome. Screenshots stay in its context. |
| `code-reviewer`, `security-auditor` | Sonnet | Read-only reviews with a written checklist. |
| Main model | Opus | Everything that needs judgment: root cause, code review verdicts, plans, scope, security conclusions. |

**Always write the model.** A subagent spawned without `model` inherits the main model (Opus). Every
delegation in a command, skill or playbook names it — in the agent's frontmatter or in the call.
Fable is never a subagent model, except as a reviewer in a session whose prompt contained `#fable`.

## The return contract — SUMMARY vs RAW (critical)

The caller tells the agent which mode. Getting it wrong either wastes the saving or silently breaks
the step:

- **SUMMARY** — the main model needs only the conclusion (a file list, "used in 4 places: …", a
  status). The raw payload is dropped. **Big saving.**
- **RAW / COMPLETE** — the main model will judge the bytes itself (a stack trace, a diff to review,
  a PHPStan list). Returned unabridged — a lossy summary corrupts the judgment. **Small saving.**

**If the answer is RAW, ask whether to delegate at all.** RAW lands in the main context anyway, so
the only saving is the cheap tool run. Delegate RAW only when the run is noisy or slow.

## Find-then-verify — the one way a cheap model touches review work

A cheap model may run a finding pass when the rule it applies is written down, and only if:

1. It returns structured findings (`file:line`, severity, what, fix) — never file contents, never a
   verdict on the change as a whole.
2. The main model re-opens every serious finding at its `file:line` before it enters a report.
3. A miss is cheap. Verification catches a false positive, never a false negative — so the pass may
   cover conventions and mechanical checks, and never security or root cause.

## When NOT to delegate

- **The payload is already in the main context** (opening a PR with text the main model just wrote).
- **The change set is small** — as a guide, under ~10 files or ~400 diff lines. A spawn costs a
  30–60 s round trip plus its own base context (~60k tokens), and the main model often has to read
  the same diff anyway. When in doubt at the boundary, stay inline.
- **A tiny single read** — a direct `grep -n` / `sed -n` is cheaper than a spawn. Batch several
  reads into one subagent, or do them inline.
- **The step needs the conversation's own context** (a handoff synthesis) — a subagent does not
  have it.

## Cursor fallback

Cursor cannot pin a per-command model and has no Task-style subagent. Every delegation instruction
degrades to: **no subagent tool → run the step inline on the active model.** The command must stay
correct without the delegation.
