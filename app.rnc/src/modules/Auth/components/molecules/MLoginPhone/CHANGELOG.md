# Changelog — MLoginPhone

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.0.0 — 2026-09-16

### Changed

- **BREAKING:** the step no longer navigates. It asks `RequestPhoneCodeService` for a code and emits `onSuccess(response, typedPhone)`; the view decides what comes next. Takes `initialPhone` so "change number" comes back filled in.
- The number is validated as a Brazilian phone with its area code before anything is sent, and the field says `autocomplete="tel-national"`.
- A throttle or a delivery failure is named in the toast instead of "something went wrong".

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
