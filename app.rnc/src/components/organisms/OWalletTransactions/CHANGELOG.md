# Changelog — OWalletTransactions

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.1 — 2026-09-17

### Changed

- Colours are role tokens (`bg-panel`, `border-line`/`border-line-strong`, `text-heading`/`text-body`/`text-muted`) instead of raw `surface-*`/`ink-*` shades, so the card flips with the colour scheme (2026-06-11, 1b0ba5d).
- The template translates with the global `$t()`; `useI18n` is no longer imported (2026-07-17, 698639d).
- Blank-line formatting only (2026-09-06, 179a733).

Entries recorded after the fact: the code shipped without them.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`OWalletTransactions.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
