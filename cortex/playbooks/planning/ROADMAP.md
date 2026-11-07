# <Product> — Build Roadmap

> Phase-by-phase plan. Tracker: `cortex/plans/phases/README.md`. Decisions: `PRODUCT-MEMORY.md`.

## Context — why this plan exists

## Operating model (agreed with <owner>, <YYYY-MM-DD>)

- **Phase = one build session = one capability someone can QA the next morning.**
- **A full vertical slice per phase:** API (migration → model → DTO → service → repository →
  controller → resource → route) + app (module, view, services) + tests. Each PR is demoable.
- **A branch and a PR per phase**, with a QA checklist in the PR. One concern per PR.
- The conventions are not negotiable: `arcus-api-architecture` and the `celer-*` rules; the login
  flow and `UsersView` are the reference chains.

## What the bootstrap already gives (verified <YYYY-MM-DD>)

Its own section, and verified by reading the code on the date written above — never from memory,
and never from this bootstrap's README. Planning a phase that re-builds something the bootstrap
already has is the most expensive planning mistake there is, and the cheapest to avoid.

| Area | State in the bootstrap | Implication for this product |
| --- | --- | --- |
| Auth, users, roles, permissions | | |
| Registration and e-mail verification | | |
| Pagination, list organisms, dialogs | | |
| Extension points the sync never overwrites (`*.local.*`) | | New modules hang off these |
| Testing harness | | |

## The roadmap

### Block A — <name> *(what is deliberately NOT in it)*

- **A1 — <capability>** — <one paragraph: what, depends on, reachable from>.

## Discipline between phases

- Only the next spec is written. Each spec starts with what the previous merge actually produced.
- A phase that edits a bootstrap unit records the divergence in that unit's `RULES.md`.

## Verification — how each morning's QA works

## Decisions (dated)

## Open items
