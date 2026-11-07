# Changelog — src/components/

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.1 — 2026-09-17

### Changed

- `unifiedClassBinding.spec.ts` and `motionTokens.spec.ts` read every template in the app — `modules/`, `views/` and `App.vue` as well as `components/` and `layouts/` (CEL-16). The class scan found `BrandView` at once; the duration scan found nothing outside the shared units.

## 1.2.0 — 2026-09-17

### Added

- `unifiedClassBinding.spec.ts` and `motionTokens.spec.ts`: scans over `components/` and `layouts/`
  for a split `class`/`:class` and for literal `duration-*` classes. Both failed on the code they
  were written against (MWalletCard, OListFeed; AColorSchemeToggle).
- `organisms/rowActions.spec.ts`: the row actions of the five list organisms are named and ask for
  44px under a finger, 12px apart.
- `atoms/ANavDot/` got its `RULES.md` and `CHANGELOG.md`.

## 1.1.0 — 2026-09-16

### Added

- `molecules/brazil/` with `MAddressFields` and `MLocalityFields` (from chapaja), re-exported from
  `molecules/index.ts`.

### Changed

- `RULES.md`: "no API calls in a molecule" now means our API. A molecule may call a public utility
  lookup (ViaCEP, IBGE) from `@Services`; the old wording is kept under the rule as retired, with
  the reason.

## 1.0.2 — 2026-09-15

### Added

- Barrel re-export of `OPaginator` from `organisms/index.ts`.

## 1.0.1 — 2026-07-15

### Changed

- Move `MPersonalizationVisualCard` to the Personalization module (single-consumer, domain-specific).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
