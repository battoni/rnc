# Changelog — MLoginPasswordCredentials

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.1 — 2026-09-14

### Fixed

- `autocomplete` reaches the password `<input>` through `:inputProps`. On `Password` a bare `autocomplete` lands on the wrapper `<div>`, where no password manager looks (from lifeshifter).

## 1.2.0 — 2026-09-09

### Changed

- Error handling moved to `useLoginError`; the form no longer owns `apiError` or its own `.catch`.

## 1.1.0 — 2026-09-09

### Changed

- Login posts to real `auth/login` via `LoginPasswordCredentialsService`; mock identifiers removed.
- Failed credentials show the API 422 message instead of redirecting to onboarding.
- HTTP 429 shows `auth.login.tooManyAttempts` instead of the generic error toast.

### Added

- Integration tests for successful `auth/login` and HTTP 429.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
