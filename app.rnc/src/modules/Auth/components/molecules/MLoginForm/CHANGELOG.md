# Changelog — MLoginForm

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.1 — 2026-09-16

### Changed

- **Correction to 1.3.0.** That entry said the parent's login "refused in silence". It does not:
  `MLoginPhone`, `MLoginPasswordCredentials` and `MLoginUsernamePassword` — the three molecules the
  login flows actually render — already show inline `Message`s. This molecule was the only one that
  did not, and it has no call site, so the change is parity between siblings rather than a defect
  fixed on a screen. The inline errors and their test stay; the reasoning above them was wrong.
- `RULES.md` no longer claims `LoginUsernamePasswordFlowView` renders this molecule, and records the
  missing call site under `## Known Gaps`.

## 1.3.0 — 2026-09-16

### Changed

- Yup errors are shown inline, under the field. **This retires the rule that said the invalid state
  was enough**: an empty submit left both fields with a red ring and `aria-invalid` and no text
  anywhere — the form refused and never said what it wanted, and a screen reader announced
  "invalid" with nothing after it. `AFormError` keeps what it was for: what the API refused
  (from lifeshifter). Covered by `MLoginForm.integration.spec.ts`.

## 1.2.1 — 2026-09-14

### Fixed

- The password `<label for="password">` pointed at nothing: `Password` had no `inputId`. It has one, and both fields now carry their `autocomplete` — the username directly, the password through `:inputProps`.

### Added

- Integration test that both labels reach their inputs and both inputs announce themselves to a password manager.

## 1.2.0 — 2026-09-09

### Changed

- Error handling moved to `useLoginError`; the form no longer owns `apiError` or its own `.catch`.

## 1.1.0 — 2026-09-09

### Changed

- HTTP 429 shows `auth.login.tooManyAttempts` instead of the generic error toast.

### Added

- Integration tests for successful login and HTTP 429.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
