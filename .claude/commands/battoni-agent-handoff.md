# Agent Handoff

> Produce a dense, **AI-to-AI** handoff document so another agent — a new chat, a different model, or a future you — can resume this work **with zero prior context**. Read-only except for the one file it writes.
>
> **Usage:** `/battoni-agent-handoff [topic]` — e.g. `/battoni-agent-handoff cortex-port`, `/battoni-agent-handoff auth-2fa`. Run from the rnc monorepo root.
>
> Not to be confused with `make handoff` (the client-delivery eject). This produces a resume-work note for another agent.
>
> **Feature work has its own handoff:** when the work is a feature with an ID
> (`specs/<id>-*/`), do not write a `handoffs/` file — rewrite that feature's `state.md`
> instead (format in `.claude/feature-flow.md`, "state.md"): Done with suite counts, Next with the
> exact command, Pitfalls. Use this command for everything else (cortex work, investigations,
> small-track work that spans days).

---

## Step 0 — This is written for a machine, not a human

The reader has **read nothing** — not this conversation, not the codebase. Write for a capable agent starting cold: terse, precise, **exact paths / commit hashes / runnable commands**, no narrative, no praise, no recap of what "we" did emotionally. Every line must help the next agent act. If a fact isn't load-bearing, cut it.

## Step 1 — Scope

- **Topic:** use the argument if given; otherwise ask: *"What's this handoff about (short topic)?"* Slugify it (lowercase, `-` for spaces) for the filename.
- Identify which **sub-projects** the work touches (`api.rnc`, `app.rnc`, `cortex`, `codelumen`, root).

## Step 2 — Gather real state (inspect, don't recall)

This is a single monorepo — one git tree. Run:

```bash
git branch --show-current
git log --oneline -8
git status --short
```

Capture: current branch, last relevant commit hash(es), and **exactly what's committed vs uncommitted**. Note any background jobs, running dev servers (`make app`, `make portal`), or pending external work (CI, Vercel preview, a PR awaiting review).

## Step 3 — Write the handoff

Save to **`handoffs/HANDOFF-<slug>-<YYYY-MM-DD>.md`** (today's date; create `handoffs/` if missing). Use this structure; omit a section only if genuinely N/A:

```markdown
# Handoff — <title>
> For the next AI agent. Written <YYYY-MM-DD>. Reader has no prior context — read this fully, then load the pointers below before acting.

## Objective
<What we're trying to achieve and why — 2-4 sentences. The "definition of done".>

## Load first (cold-start checklist)
- Read `CLAUDE.md` (always-on monorepo context).
- `/battoni-start-session` to load the rule set(s) for the sub-project(s) in play.
- Read these specific files for this task: `<path>` — <why>; `<path>` — <why>.

## Status
- [x] <done item>
- [~] <in-progress item — exactly where it stands>
- [ ] <not-started item>

## Decisions locked (do NOT re-litigate)
- <decision> — <one-line why>.

## State of the world
- Branch: `<branch>` · base for PRs: `development` if the remote has it, else `main`.
- Last commit: `<hash>` <subject>.
- Uncommitted: <files, or "clean">.

## Files touched
- `<path>` — <created|modified>: <one-line purpose>.

## Gotchas & environment
- <non-obvious fact the next agent will trip on>: <detail / the command that works>.

## Open questions / blockers (need the developer)
- <question> — <why it blocks, what the options are>.

## Next steps (ordered, concrete)
1. <action> — `<exact command if any>`.

## Verify
- `<command>` → expect `<result>`.
```

Rules for the content:
- **Decisions section is the point** — capture every choice already made + its reason, so the next agent doesn't reopen settled questions or contradict them.
- **Be concrete**: `app.rnc/src/modules/Auth/.../MLoginEmail.vue:42`, real `git log` hashes, real `make` targets — never "the relevant file".
- **Committed vs uncommitted must be unambiguous** — the next agent needs to know what's safe and what's in-flight.
- Keep it **self-contained**: if a convention matters, name the rule file to read rather than re-explaining it.

## Step 4 — Report back

State the saved path, a 3-line summary (objective · where it stands · the single most important next step), and offer: *"Paste the file's contents into the new chat to resume."* Write nothing else; this command only produces the handoff file.
