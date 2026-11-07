# Changelog — MConfirmPopup

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.1 — 2026-09-17

### Fixed

- Cancel and confirm are `gap-3` apart instead of `gap-2`: under a finger their grown hit areas
  overlapped by 4px and the destructive button won. The preset's footer gap does not reach this
  custom `#container`.
- `RULES.md`: the labels are rendered as given, so they must be translated strings — the old line
  also allowed an i18n key, which would render as the key.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`MConfirmPopup.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
