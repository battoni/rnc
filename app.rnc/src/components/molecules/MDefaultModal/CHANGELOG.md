# Changelog — MDefaultModal

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.2 — 2026-09-17

### Fixed

- The icon-only close button has an `aria-label` (`common.actions.close`); a screen reader announced
  an unnamed button.

## 1.1.1 — 2026-09-14

### Changed

- `rounded-xl` instead of `rounded-2xl`, which is not on the radius scale, and `shadow-lift-lg` instead of Tailwind's generic `shadow-xl` — a modal floats, and floating is the theme's large relief (from lifeshifter).

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`MDefaultModal.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
