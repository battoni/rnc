# Changelog — MSignUpUser

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.5.0 — 2026-09-16

### Changed

- The birth date is converted with the shared `fromDayMonthYear` + `toLocalDateString` instead of a local string swap; a day that does not exist (31/02) is sent as typed for the API to refuse (lifeshifter-265, chapaja-272). New `MSignUpUser.spec.ts`, run in Tokyo, where a `toISOString()` would send the day before.

## 1.4.0 — 2026-09-16

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

## 1.3.1 — 2026-09-14

### Fixed

- `autocomplete` reaches the password `<input>` through `:inputProps`. On `Password` a bare `autocomplete` lands on the wrapper `<div>`, where no password manager looks (from lifeshifter).

## 1.3.0 — 2026-09-14

### Added

- Optional phone with a Brazilian mobile mask and a WhatsApp icon, validated for area code + 8–9 digits (from chapaja).

### Changed

- Validation runs on submit only, so no field turns red before the person asks to send the form (from chapaja).

## 1.2.0 — 2026-09-09

### Fixed

- A field's server error clears when that field is edited, instead of surviving until the next submit and telling someone their brand-new e-mail is already taken.
- A 422 message that is not an i18n key no longer reaches the field through `translateError`, which wrapped Laravel's English in the `errors.unexpected` template. Known shapes are named; anything else says, in the reader's language, that the field needs another look.

## 1.1.0 — 2026-09-09

### Changed

- Prefills email from `?email=` when present.
- Maps API 422 field errors onto the form (including taken email/CPF copy).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
