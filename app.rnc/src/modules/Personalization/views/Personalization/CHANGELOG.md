# Changelog — PersonalizationView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-09-17

### Added

- An `<ATip tip-key="personalization.appearance">` in the page header's `#titleSuffix` (2026-09-04, e97bba3).

### Changed

- The content wrapper is a `<div>`, not a `<main>`: the layout already renders the page's `main` landmark, and a second one nested inside it is invalid (2026-09-06, 179a733).

Entries recorded after the fact: the code shipped without them.

## 1.0.0 — 2026-07-13

### Added

- Personalization page with intro copy and `MPersonalizationVisualCard`.
- Routes `/personalization` and `/personalizacao`.
- Unit tests (`PersonalizationView.view.spec.ts`).
