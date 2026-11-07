# Changelog — MLoginUsernamePassword

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.4.1 — 2026-09-14

### Fixed

- `autocomplete` reaches the password `<input>` through `:inputProps`. On `Password` a bare `autocomplete` lands on the wrapper `<div>`, where no password manager looks (from lifeshifter).

## 1.4.0 — 2026-09-09

### Changed

- Error handling moved to `useLoginError`; the form no longer owns `apiError` or its own `.catch`. Drops the `TODO-ID-47` that sat on the duplicated block.

## 1.3.0 — 2026-09-09

### Changed

- HTTP 429 shows `auth.login.tooManyAttempts` instead of the generic error toast.

## 1.2.0 — 2026-06-01

### Added

- `data-testid="login-username"` on the username InputText.
- `data-testid="login-submit"` on the submit Button.

## 1.1.0 — 2026-05-30

### Added

- Integration tests (`MLoginUsernamePassword.integration.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
