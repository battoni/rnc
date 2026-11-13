# Changelog — Arcus Build & Config

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.0 — 2026-11-07

### Added

- `config/assistant.php` (rnc-3 F4): `driver` (`ASSISTANT_DRIVER`, default `curated`) names the Reforma assistant's `ReformaAnswerer` in `AppServiceProvider::ASSISTANT_DRIVERS`, whose only entry is `curated`; an unknown name stops the app at boot. Documented in `.env.example`.

## 1.2.0 — 2026-11-07

### Added

- `config/channel.php` (rnc-3 F3a): the RNC channel's defaults for a new channel firm — `monthly_fee_cents` 350000, `share_bp` 1500. No `env()`: they are product defaults, not deployment settings; each firm stores its own copy.

## 1.1.0 — 2026-09-16

### Added

- `config/data.php` enables spatie/laravel-data's `FormRequestNormalizer` ahead of `ArrayableNormalizer`, so `XxxDTO::from($request)` maps `$request->validated()` instead of `$request->all()`: a field without a rule, or one an `exclude_*` rule removed, no longer reaches the DTO. Covered by `tests/Feature/DtoValidatedInputTest.php`, red with the file removed and red with the two normalizers swapped (from chapaja-116).

### Fixed

- `http://localhost:5173` — where `npm run dev` and the Playwright suite serve app.rnc — is in the default `CORS_ALLOWED_ORIGINS` and in `.env.example`. It was already a Sanctum stateful domain, so the SPA opened there answered with a blank screen. Covered by `tests/Feature/CorsTest.php` (from lifeshifter-553).

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
