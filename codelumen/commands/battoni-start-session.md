---
title: /battoni-start-session
outline: deep
---

::: info Generated page
This page is generated from `.claude/commands/battoni-start-session.md` — run it with `/battoni-start-session`. **Edit the source command, not this page.**
:::

# Start Session

Load the right rules before planning or working on a feature.

## Step 1 — Ask which projects

Ask the user: "Which project(s) are you working on — api.rnc, app.rnc, codelumen, vitrum, liquen, or cortex?"

## Step 2 — Load rules

**Always** — read `.claude/rules/shared-conventions.md` (it applies to every sub-project).

**api.rnc** — read `.claude/rules/arcus-api-architecture.md`

**app.rnc** — read all nine files (the canonical set is `celer-01` through `celer-09`):

- `.claude/rules/celer-01-vue-checklist.md`
- `.claude/rules/celer-02-vue-imports.md`
- `.claude/rules/celer-03-vue-script.md`
- `.claude/rules/celer-04-vue-template.md`
- `.claude/rules/celer-05-module-conventions.md`
- `.claude/rules/celer-06-design-system.md`
- `.claude/rules/celer-07-view-patterns.md`
- `.claude/rules/celer-08-rules-auto-injection.md`
- `.claude/rules/celer-09-brand-mark-contract.md`

**codelumen, vitrum, liquen, cortex** — no rules configured yet. Acknowledge this to the user and proceed without loading rules.

## Step 3 — Confirm

Reply with a single line confirming what was loaded. For example:

> Loaded shared + api.rnc + app.rnc rules. Ready — what are we building?

Or if a project has no rules yet:

> No rules configured yet for vitrum. Ready — what are we building?
