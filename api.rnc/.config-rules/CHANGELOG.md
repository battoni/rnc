# Changelog — Arcus Build & Config

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-09-16

### Added

- `config/data.php` enables spatie/laravel-data's `FormRequestNormalizer` ahead of `ArrayableNormalizer`, so `XxxDTO::from($request)` maps `$request->validated()` instead of `$request->all()`: a field without a rule, or one an `exclude_*` rule removed, no longer reaches the DTO. Covered by `tests/Feature/DtoValidatedInputTest.php`, red with the file removed and red with the two normalizers swapped (from chapaja-116).

### Fixed

- `http://localhost:5173` — where `npm run dev` and the Playwright suite serve app.rnc — is in the default `CORS_ALLOWED_ORIGINS` and in `.env.example`. It was already a Sanctum stateful domain, so the SPA opened there answered with a blank screen. Covered by `tests/Feature/CorsTest.php` (from lifeshifter-553).

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
