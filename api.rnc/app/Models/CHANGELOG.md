# Changelog — App Models (User)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.1 — 2026-09-15

### Fixed

- `User` `@property UserStatus|null $status` so static analysis treats the cast enum as a real
  type instead of a string (and stops calling every `=== UserStatus::…` dead code).

## 1.1.0 — 2026-05-30

### Added
- Fix UserPermission model: add $timestamps = false (table has no timestamp cols).

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
