# Changelog — Auth DTOs

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.0 — 2026-09-17

### Added

- `CheckUsernameDTO` (review ARC-14).

## 1.2.1 — 2026-09-16

### Changed

- `RULES.md`: a DTO is built with `XxxDTO::from($request)`, which maps the validated input through the `FormRequestNormalizer` enabled in `config/data.php` (from chapaja-116).

## 1.2.0 — 2026-09-16

### Added

- `LoginCodeRequestDTO` and `LoginCodeVerifyDTO`.

## 1.1.0 — 2026-09-16

### Added

- `MeUpdateDTO` — the two self-service fields.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
