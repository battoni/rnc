# Changelog — OPaginator

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-09-17

### Changed

- The page report is `common.paginator.report` ("Page 2 of 3" / "Página 2 de 3") instead of
  PrimeVue's English `(2 of 3)`.

### Fixed

- Under a coarse pointer the four page buttons are 44px and 12px apart (bound `pt`); they were 40px,
  4px apart, so a thumb aiming at "next" landed on "last".

## 1.0.0 — 2026-09-14

### Added

- Initial documented baseline (from chapaja): a thin wrapper over PrimeVue `Paginator` that speaks
  1-based pages (`page` in, `onPage` out) instead of PrimeVue's 0-based `first`/`page`,
  hides itself on a single page, and pairs with `usePaginatedList`.
- Unit tests (`OPaginator.spec.ts`).
