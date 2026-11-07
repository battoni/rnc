# Changelog — Shared services

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-09-16

### Changed

- `RULES.md` names the consumers: `useCepLookup` → `MAddressFields` / `MLocalityFields` for the
  CEP lookup. The IBGE service still has none.

## 1.0.0 — 2026-09-14

### Added

- `@Services` alias (vite, tsconfig, vitest) and `brazil/` — ViaCEP address and IBGE municipalities, client-side, never throwing (from chapaja).
