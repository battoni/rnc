# Changelog — MLoginCode

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.2 — 2026-09-17

### Fixed

- The "please wait" toast on a resend inside the cooldown was `severity: 'error'`. A wait is a refusal on a rule, not a failure, so it is `warn` (celer-07, Refusals). The spec now checks the severity.

## 1.3.1 — 2026-09-17

### Fixed

- An empty code said "Invalid code" instead of asking for one: `.length()` was declared before `.required()`, and an empty string fails both. `required` now comes first (lifeshifter-473, from lifeshifter 266cf22). Covered by a new spec that submits the field empty.

## 1.3.0 — 2026-09-16

### Fixed

- The resend cooldown is `useCountdown`, measured by the wall clock, instead of a per-tick `setInterval` that a suspended phone left locked (largatudoevai-088). New `MLoginCode.spec.ts`.

## 1.2.0 — 2026-09-14

### Fixed

- The OTP code is read from a local ref mirrored into the Form, not from the Form: PrimeVue Forms could keep only the last digit (primevue#7025). Resolves `TODO-ID-44` (from chapaja).

## 1.1.0 — 2026-09-09

### Changed

- Successful OTP verification forwards `?email=` to `auth.onboarding` when present so signup can prefill it.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
