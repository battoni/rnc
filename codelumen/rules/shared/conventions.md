---
title: Shared conventions for the rnc monorepo — git, commits, PRs, code hygiene
outline: deep
---

::: info Generated page
This page is generated from `.claude/rules/shared-conventions.md` — **edit the source rule, not this page.** Always active across the monorepo.
:::

# Shared Conventions

These rules apply across all sub-projects in this monorepo.

## Git Commit Messages

- Use imperative mood: `Add feature`, `Fix bug`, `Remove unused code` — not `Added`, `Fixed`, `Removed`
- Keep the subject line under 72 characters
- No period at the end of the subject line
- Separate subject from body with a blank line when a body is needed
- Body explains the *why*, not the *what*: the symptom as it was seen, the real cause, the number
  measured before and after for anything visual ("192px left against 280 right at 1920"), and the
  alternatives that were rejected with the reason
- A commit that applies a review lists the findings it did NOT apply, and why — otherwise the next
  reviewer raises them again and nobody remembers they were decided
- A commit that closes a batch of work ends its body with the count of each suite that ran (e.g.
  `363 Pest · 686 Vitest · 43 Playwright, all green`). A suite that stopped growing, or shrank, is
  then visible in the history instead of only in a CI log nobody reopens.

## Branch Naming

- `feature/short-description` — new functionality
- `fix/short-description` — bug fix
- `refactor/short-description` — code change without behaviour change
- `chore/short-description` — tooling, dependencies, config

## Pull Requests

- One concern per PR — do not mix features, fixes, and refactors
- PR title follows the same imperative rules as commit messages
- Include a description that explains *why* the change is needed, not just what changed
- Keep PRs small enough to review in one sitting

## Rules and RULES.md

- **A rule that stops holding is retired with its reason, never deleted in silence.** Replace it
  with what holds now and say in one sentence why the old one was wrong or no longer applies. A
  vanished rule reads as an accident, and the next person re-adds it.
- **A known gap goes in the unit's `RULES.md`, not in memory or a chat.** An open decision, a hole
  shipped on purpose, a path not yet verified live — written under `## Known Gaps` with the reason,
  where the next person editing the unit will read it.

## Code Hygiene

- No `console.log`, `console.error`, or `console.warn` in committed code
- No commented-out dead code — delete it, git history preserves it
- No TODO comments without a ticket reference
- Remove unused variables, imports, and functions before committing
- A new test counts only after it has FAILED against the code it guards — reintroduce the defect,
  watch it go red, restore. A test that has never been red has proved nothing: it can pass on a
  stub's attribute, a guard that skips the assertion, or a match that the old code also satisfies.
