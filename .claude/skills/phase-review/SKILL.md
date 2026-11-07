---
name: phase-review
description: "Critically review a build plan, a built phase's implementation, or a downstream plan's consistency after an upstream phase shipped — grounded in the project's source-of-intent document and in real code, ranked by severity, with the open decisions grilled and the plan revised to a definitive, build-ready spec. Load when asked to 'review the plan', do a 'full review' of a phase (plan or what we built), check that deviations during one phase were absorbed into later plans, produce a 'definitive / build-ready' plan, write a 'vN addendum', or grill the user to lock decisions."
license: MIT
metadata:
  author: rnc
---

# Phase Review — Plan & Implementation Critical Read

> **Scope:** any project built in phases, whose specs live under `cortex/plans/phases/` and whose
> **intent is fixed by a source document** — an architecture PDF, a brief, a signed spec, whatever
> the project actually agreed to. This skill is the *method* for reviewing a plan before building
> it, reviewing what was built against its plan, and keeping later-phase plans consistent when an
> earlier phase forced a deviation.
>
> **Name the source of intent before starting.** The whole method rests on there being one, and on
> everyone agreeing what it is. If a project has no such document, say so and review against the
> plans alone — with the ranking below collapsed to two levels, because "fidelity gap" has nothing
> to be measured against.

## When this applies — three modes

- **A — Plan review (pre-build).** "Review the next phase", "is this plan definitive?", "grill me so we can lock it."
- **B — Implementation review (post-build).** "Full review of what we built" — built code vs its plan + the `.claude/rules` + conventions; find deviations and judge whether they were necessary and correctly documented.
- **C — Forward-propagation (cross-plan).** After a phase ships, "review the next plan to make sure deviations during this phase were addressed" — every necessary deviation from Mode B is reconciled into the downstream plan(s) it affects. (This is exactly the Phase-3 → Phase-4 reconciliation in the canonical examples.)

A "full review of phase N" usually means **B then C**: review the build, then re-review N+1's plan.

## First principles (all modes)

1. **Three sources, ranked.** The **source document is canonical for intent**; the **plans are derived**; the **code is reality**. When the source and a plan conflict → the source wins (revise the plan). When a plan and the code conflict → the code wins for *what exists*, but a plan describing a contract the code must meet still governs new work.
2. **Never review from the plan text alone — ground every claim in real code.** A plan that says "copy this convention" is a hypothesis until verified. Fan out **`Explore` agents** (`model: "sonnet"`, SUMMARY returns — `.claude/delegation-policy.md`) to (a) confirm each "copy this" claim against the real modules, and (b) read the live schema/theme/contracts the plan depends on. **Flag loudly anything the plan asserts as an existing convention that has NO precedent in the codebase** — those are the real implementation risks.
3. **Cross-reference the source document section by section.** Walk the relevant sections and list every attribute, check or behaviour it mandates that the plan omits or contradicts. The acid test is to pick **one fully-specified real case from the source** — the most completely described example it contains — and ask whether the plan can express it end to end. If a real case's own inputs and rules cannot be modelled, that is a blocking gap, not polish. A plan that satisfies every abstract requirement and no concrete instance has not been checked.
4. **Check adjacent plans.** Upstream contracts the plan consumes, and downstream plans that consume this one. A change to an engine/evaluator/value-model contract ripples forward.
5. **Rank by severity, always with a recommended fix.**
   - 🔴 **Blocking design gap** — can't express the real product, or breaks a downstream contract.
   - 🔴 **New-pattern risk** — required by the plan but no codebase precedent (JSON columns, recursive builders, reorder, etc.). De-risk with a spike + priority tests.
   - 🟡 **Fidelity gap** — an attribute, check or behaviour the source mandates, missing or misstated.
   - 🟢 **Minor / note** — precision, naming, derivable fields, illustrative-vs-normative.
6. **Separate "I decide" from "your call."** Resolve low-stakes items yourself with sensible defaults (and say so). **Grill the user only on what materially changes scope/schema/behavior.**
7. **Be honest, not reassuring.** If a recent change (e.g. a theme overhaul) means the rules doc may lag reality, say so and verify against code. Name what you'd get wrong without grounding.

## The grill (AskUserQuestion)

When a decision is genuinely the user's, ask via `AskUserQuestion`:
- **Recommendation first**, labeled `(Recommended)`, with a one-line *why*.
- Each option explains the trade-off (faithfulness vs scope vs complexity).
- Use **`preview`** for schema/behavior comparisons (the user picks by seeing the shape).
- Only ask what changes the build. Batch up to 4; fold the rest into "I'll decide these."

## Revising to definitive (Modes A & C)

After decisions are locked, make the plan self-consistent and authoritative:
1. **Write a `vN — <absolute date>` addendum at the top** of the plan: a numbered, authoritative list of the decisions/changes, with a line stating *"where the body shows the old shape, this section wins."* This is the source of truth for the deltas.
2. **Patch every inline block the addendum changes** (schema, migrations, enums, services, interfaces, views, tests, checklist, alignment) so nothing contradicts. Tag patched lines with the addendum section (`v2 §4`, `v2 R1`, …).
3. **Stamp a top banner:** `## ✅ STATUS: DEFINITIVE — build-ready (<date>). Decisions locked.`
4. **Reconcile pointers** in `00-master-plan.md` and any downstream plan that references the changed contract (the detail doc's addendum is authoritative over the master sketch).
5. **List cross-phase touches explicitly** in the plan (edits that reach back into already-built phases) so they aren't forgotten and the earlier plans need not be reopened independently.
6. **Consistency sweep:** `grep` for the old shapes you replaced (stale enum values, old node lists, old triggers) and confirm only the addendum *describing* the change remains.

## Implementation review specifics (Mode B)

1. **Mechanical convention pass first** — reuse the existing review commands: `/battoni-review-arcus-code` (the API), `/battoni-review-vue-conventions` + `/battoni-review-design-conventions` (the app). These cover the `.claude/rules` + the per-unit **cortex metadata gate** (RULES.md + CHANGELOG.md present + version match) — a blocking item, not cosmetic. A repo without an app or an API has none of these commands: run `code-reviewer` on the diff instead. The metadata gate binds only units that have a `RULES.md`; where none exist, say so in the review instead of leaving the gate open.
2. **Judgment pass** — did the implementation **deviate from the plan**? For each deviation: was it **necessary** (the plan was wrong/incomplete) or **drift** (sloppy)? Necessary deviations are fine — but they must be **documented** (CHANGELOG + the plan updated) and, critically, **propagated** (Mode C).
3. **Verify the real product, not just units** — reachability + an end-to-end journey on a fresh seeded DB (see memory `verify-user-journeys`), and the §15.1 product as the worked example.

## Forward-propagation specifics (Mode C)

For every **necessary deviation** found in Mode B, find the downstream plan(s) whose contract it changes and run Mode A's revision on them: a new `vN` addendum reconciling the deviation, inline patches, re-stamp. The goal: a later phase never consumes a contract that the earlier phase already changed (the bug this whole pattern exists to prevent).

## Deliverables & housekeeping

- A **severity-ranked findings report** (🔴/🟡/🟢, each with a recommended fix and a citation to the source document or to real code), then the grill, then the revised plan(s).
- **Record state in memory** (`phase-N-plan-review`): decisions locked, addendum sections, cross-phase touches, what's built vs planned, committed-or-not.
- **Commit only when the user asks** (shared-conventions git rules). Stamp the plan; don't push unprompted.

## Reference

- The source-of-intent document — canonical intent. Read it; never review from memory. A review
  that quotes a document nobody reopened is a review of what you remember it saying.
- `cortex/plans/phases/README.md` — the phase loop, the tracker, and what "reachable entry point"
  means. It is the file this method reviews against when asking whether a phase is done.
- The project's own source-of-intent document, wherever it lives. Name it in the review; a reader
  six months later has no way to know which document you meant.
- `.claude/rules/` + `.claude/commands/review*` — the convention baselines the mechanical pass enforces.
- Memory: `phase-3-plan-review`, `phase-4-plan-review`, `verify-user-journeys`, `migrations-edit-originals`.
