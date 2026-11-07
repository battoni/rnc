# Changelog — MWalletCard

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.2 — 2026-09-17

### Changed

- The two titles carry one `:class` array instead of a static `class` beside a bound `:class`
  (celer-04). No visual change; `src/components/unifiedClassBinding.spec.ts` guards it.

## 1.1.1 — 2026-09-17

### Changed

- Colours are role tokens (`bg-panel`, `border-line-strong`, `text-heading`, `text-muted`) instead of raw `surface-*`/`ink-*` shades, so the card flips with the colour scheme (2026-06-11, 1b0ba5d).

Entry recorded after the fact: the code shipped without it.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`MWalletCard.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
