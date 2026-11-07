---
version: 1.0.0
origin: pendulum
based-on: 1.0.0
---

# RULES — Showcase Module

> For AI agents. Last updated: 2026-09-17.

## Purpose

The living references: the brand (`BrandView`), the shared components (`ComponentsView`), the
feature catalogue (`FeaturesView`) and the page-level form patterns (`ShowcaseView`, with
`MShowcaseShortForm` and `MShowcaseLongForm`).

## Intentional Decisions

- **These pages are documentation that cannot go stale silently.** A written catalogue drifts; one
  that renders the real components fails visibly when they change.
- **Everything here is a fixture.** The forms submit nothing, the rows are constants, the people are
  invented, and the vocabulary is neutral — no product's domain. Nothing in this module may reach a
  real endpoint.
- **The gate is `design.read`, not a role.** Every route declares `meta: { permission: 'design.read' }`
  and the global auth guard enforces it; the module has no `beforeEnter`. A permission can be
  granted from the permissions screen; a hard-coded role name cannot.
- **`ComponentsView` may be large.** It is a catalogue, and splitting it would only hide how much
  surface there is to maintain.
- **`features.generated.json` is generated** (`npm run build:features`) from every unit's
  `RULES.md`/`CHANGELOG.md`. Do not edit it by hand; run the script after a version bump.

## Do Not

- **Do not use these pages to try things out.** A reference is read as a statement of how the system
  works; experiments belong in a branch.
- **Do not call a service from a showcase form.**
