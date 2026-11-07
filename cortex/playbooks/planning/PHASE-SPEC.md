# <id> — <capability>

> Spec for ONE phase. Written only when this phase is next, from what the previous one merged.
> Tracker: `cortex/plans/phases/README.md` · Decisions: `PRODUCT-MEMORY.md`.

The anatomy is fixed on purpose. Every section below exists because a phase once shipped without it:
a state nobody owned, a screen nobody could reach, scope that grew overnight without anyone deciding
it should, and a next phase that started from the plan instead of from what was built.

## Goal

<Two or three sentences. What a person can do after this merges that they could not before.>

## State graph — and who drives each transition

| From | To | Driven by | Guard |
| --- | --- | --- | --- |

## Product calls taken

Decided, documented, flagged — each with its `D<n>` or marked as a build decision for QA to confirm.

Backend and Frontend below are the anatomy of a repo with an API and an app. A repo with only one of them drops the
other; a repo with neither (a site-only repo) replaces both with its own units — pages,
components, data files and the tests its `make pre-commit` runs — under the same rule: every
unit the phase touches is named.

## Backend

### Migrations (edit the `create_*`, never an ALTER — `arcus-api-architecture` §3)
### Enums
### Models
### Services
### Repositories
### Controllers, requests and routes
### Resources
### Tests (Pest)

## Frontend

### Services and interfaces (resource shape ≠ payload shape)
### Views and components
### i18n
### Tests (Vitest)

## Reachability — verify in Chrome after `vite build`

<The exact click path from a screen that already exists to every new screen.>

## Out of scope

<What this phase deliberately does not do, and where each item goes instead. Anything built that is
not in the spec and not in this list is a deviation to report, not a bonus.>

## Definition of done

- [ ] <acceptance criterion a QA person can check>

## Post-build drift check

<Filled after the build: every place the built thing differs from this spec, and why.>

## Carry-forward to <next id>

<What the next spec must start from: decisions locked here, debts left, shapes that changed.>
