# Changelog — FirmNotFoundView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-11-14

### Changed

- RULES: the flag is `useFirmBrand().isFirmMissing` (rnc-4 F2b, D10). Doc only.

## 1.0.0 — 2026-11-13

### Added

- The view (rnc-4 F1b, R3): "Escritório não encontrado", a hint and a link to the root, shown by
  `TheRouteGuard` on a subdomain the API answers with 404 `firms.notFound`.
