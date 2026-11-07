# Changelog — FeaturesView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.2 — 2026-09-16

### Fixed

- The intro lines, counts, origin and descriptions (the chevron icon stays `subtle`): `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).
- The expander chevron had both `class` and `:class`; they are one `:class` array (`celer-04`).

## 1.1.1 — 2026-09-14

### Changed

- Gated on the `design.read` permission through `meta.permission`, enforced by the global auth guard, instead of a `beforeEnter` comparing the role slug with the administrator's. A role check cannot be granted from the permissions tab. `modules/Showcase/guards.ts` is gone (from lifeshifter).

## 1.1.0 — 2026-09-04

### Added

- A **Tooling** section listing what `.claude/` and `cortex/` ship — skills, agents, commands,
  rules, hooks and the cortex subsystems. Read from what each kind already declares rather than
  from a metadata triad it does not have, and cut at the first sentence so the section stays
  scannable.

## 1.0.0 — 2026-09-04

### Added

- Initial documented baseline: read-only catalogue of the codebase's units, grouped into
  features, generated from the per-unit metadata layer by `app.rnc/scripts/build-features.mjs`.
