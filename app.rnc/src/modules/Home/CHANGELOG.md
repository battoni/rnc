# Changelog — Home Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.0.0 — 2026-09-09

### Removed

- **BREAKING — `FinanceView`.** A wallet dashboard prototype on seeded local state, with no backend coming. It went with its routes (`finance-en` / `finance-pt-BR`), its navigation entry, the `finance.overview` tip anchor and the tip the seeder put on it, and the `finance.*` locale block apart from `finance.transactions.*`, which the surviving Showcase wallet components still read.
- A project that had already deleted it by hand inherits nothing new; one that still shows it loses the screen on sync. That is the intent.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
