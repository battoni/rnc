# Changelog — Auth Resources

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.1.0 — 2026-11-07

### Added

- `UserResource` sends `partner_id` (rnc-2 F1, R1): the partner a portal login belongs to, null for staff.

## 2.0.0 — 2026-09-16

### Changed

- **Breaking:** `RoleResource` sends `id` as an integer, like `UserResource` always did. One id format on the wire, chosen by the owner (from lifeshifter-269).

## 1.2.0 — 2026-09-16

### Added

- `LoginCodeRequestedResource` — `dev_code` is absent, never null, when the code is not exposed.

## 1.1.0 — 2026-09-14

### Added

- `UserResource` carries `email`, `email_verified` (a boolean, never the timestamp), `phone` and `profile_type`, so the Users screen can show them without another request.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
