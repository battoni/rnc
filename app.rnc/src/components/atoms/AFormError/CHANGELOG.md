# Changelog — AFormError

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.1 — 2026-09-17

### Fixed

- The copy control is a `<button type="button" class="touch-target">` with an `aria-label`
  (`errors.copy`, then `errors.copied`) instead of a clickable `<i>`: the `<i>` took no focus and
  a screen reader announced nothing. The icon inside is `aria-hidden`.
- Recorded late: the intro line moved from `text-surface-600` to `text-muted` in 1b0ba5d
  (2026-06-11, the role-token migration) without an entry here.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`AFormError.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
