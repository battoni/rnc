# Changelog — APageHeaderAction

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.3 — 2026-09-17

### Fixed

- Below `md` the icon-only button is `size-11` (44px), not `size-10`: a default-size button gets no
  touch growth, so 40px was the whole target.
- The press transition is `motion-calm` instead of a literal `duration-300`, so reduced motion
  shortens it.

## 1.0.2 — 2026-09-15

### Changed

- Documented for `#primaryAction` in `ThePageHeader` (create CTAs on Perfis, Usuários, Dicas), not
  only `#actions`.

## 1.0.1 — 2026-07-06

### Changed

- Icon-only mobile buttons now render as a `size-10` square with centered icon.

## 1.0.0 — 2026-07-06

### Added

- Page header action button with responsive icon-only layout below `md`.
