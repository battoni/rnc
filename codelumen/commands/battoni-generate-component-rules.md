---
title: /battoni-generate-component-rules
outline: deep
---

::: info Generated page
This page is generated from `.claude/commands/battoni-generate-component-rules.md` — run it with `/battoni-generate-component-rules`. **Edit the source command, not this page.**
:::

# Generate Component RULES.md

> Generates a `RULES.md` for a specific folder in **app.rnc/src/**. Run from the rnc monorepo root.
>
> Usage: `/battoni-generate-component-rules` — then provide the target folder path when asked.

---

## Step 1 — Get the target folder

If a folder path was passed as an argument, use it. Otherwise ask:

> Which folder should I generate RULES.md for? (e.g. `app.rnc/src/components/molecules/MMainDialog`)

## Step 2 — Read the global rules

Read all nine app.rnc rule files (`celer-01` through `celer-09`) and the shared conventions, so you know what is already covered globally and must **not** be repeated in the RULES.md:

- `.claude/rules/celer-01-vue-checklist.md`
- `.claude/rules/celer-02-vue-imports.md`
- `.claude/rules/celer-03-vue-script.md`
- `.claude/rules/celer-04-vue-template.md`
- `.claude/rules/celer-05-module-conventions.md`
- `.claude/rules/celer-06-design-system.md`
- `.claude/rules/celer-07-view-patterns.md`
- `.claude/rules/celer-08-rules-auto-injection.md`
- `.claude/rules/celer-09-brand-mark-contract.md`
- `.claude/rules/shared-conventions.md`

## Step 3 — Read the target folder

Read every file in the target folder: `.vue`, `.ts`, `index.ts`, `interfaces.ts`, `enums.ts`, `constants.ts`, composables, etc.

Also check for any existing `RULES.md` files in parent folders up to `src/` — do not duplicate what they already say. **If a `RULES.md` already exists in the target folder, read it** — you need it for the next step.

## Step 4 — Decide CREATE / UPDATE / SKIP (idempotency gate)

Before spending effort, classify the target:

- **SKIP** — a `RULES.md` exists and still matches the code. Report *"current, no change"* and stop. **Do not rewrite a file just to touch the date.**
- **UPDATE** — a `RULES.md` exists but has drifted (code changed, or it documents something no longer true). Refresh **only** the parts that drifted; **preserve still-accurate prose and any human edits**. Do not regenerate wholesale. A diff that only *adds* lines is a smell — replace the stale line, don't append beside it.
- **CREATE** — no `RULES.md` exists. Write a fresh one.

Only CREATE and UPDATE proceed.

An **UPDATE** is a change to the unit, so it also bumps the unit (SemVer rules in
`cortex/sync/SCHEMA.md`): a new entry on top of the sibling `CHANGELOG.md`, the RULES.md
frontmatter `version` set to it, and — in rnc — `based-on` set to the same value. A CREATE
starts at `1.0.0` (Step 7).

## Step 5 — Analyze

Extract only what is **specific, non-obvious, or intentional** about this folder. Ask yourself:

- What is this component's single responsibility?
- What design decisions look wrong but are intentional?
- What must never be changed without understanding the full context?
- What are the prop/emit contracts that carry semantic meaning beyond their types?
- What edge cases does this component already handle?
- What external dependencies are not obvious from imports?

Do **not** write rules for:
- Anything covered by the global rule files read in Step 2
- Things obvious from reading the code
- Generic Vue/TypeScript best practices

## Step 6 — Write RULES.md (with frontmatter)

Write the file inside the target folder. **Start with the metadata frontmatter** (`cortex/sync/SCHEMA.md`) — a RULES.md without it, or without the CHANGELOG of Step 7, is a blocking violation of the metadata gate (A.6) in `/battoni-review-vue-conventions`. Then the body, using this structure. Omit any section that has nothing meaningful to say — do not pad with filler.

**Budget & content discipline:**

- Target **≤ ~600 words / ~4 KB** per file, so the parent chain still fits when injected (see `celer-08-rules-auto-injection`).
- **Document the durable contract, not the investigation** — what the code does now and the traps to avoid, never the refinement-chat story ("we tried a wider match, it over-matched, so…"). That narrative ages out the moment the code lands and burns tokens on every later edit.
- **Over budget → split into a child-folder `RULES.md`, don't truncate.**

```md
---
version: 1.0.0
origin: rnc
based-on: 1.0.0
---

# RULES — [ComponentName]

> For AI agents. Last updated: YYYY-MM-DD.

## Purpose
[One sentence: what this component does and its scope]

## Intentional Decisions
- [Decision]: [Why it exists — and the alternative that was rejected, with the reason, when there was one]

## Prop & Emit Contract
- `propName`: [What it semantically means, not just its type]
- `emitName`: [When it fires and what the consumer is expected to do]

## Edge Cases Handled
- [Case]: [How it's handled and why]

## Do Not
- [Specific thing to avoid and why]

## Dependencies & Context
- [Any non-obvious runtime dependency, store, composable, or parent contract]

## Test IDs
- `data-testid="…"`: [what it marks] — and any state attribute a test reads (`data-state`, …). The e2e suite selects by these, so they are contract like a prop.

## Known Gaps
- [A hole shipped on purpose, an open decision, or something not yet verified live — and why]
```

In rnc, `based-on` always equals `version`. In a derived project, a unit the project
authored itself takes `origin: <project>` — `cortex/sync/SCHEMA.md` says what `based-on` records
there.

## Step 7 — Create the sibling CHANGELOG.md

Every RULES.md has a co-located CHANGELOG.md — the canonical version source; the RULES.md
frontmatter `version` mirrors its top entry. On CREATE, write it in the same folder:

```md
# Changelog — [ComponentName]

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.0 — YYYY-MM-DD

### Added
- Initial documented baseline.
```

On UPDATE, add the new version's entry on top instead (Step 4). The two files are born together
and stay version-aligned.
