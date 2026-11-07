# Changelog — VerifyEmailView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.1 — 2026-09-17

### Fixed

- The resend countdown ("You can ask again in 59s") is a sentence, so it takes `text-muted`, not `text-subtle` — 4.09:1 light and 3.90:1 dark, under the 4.5 floor (VIS-09, chapaja-207). The countdown spec asserts the class.

## 1.3.0 — 2026-09-16

### Added

- A 60s resend cooldown shown beside the button, measured by the wall clock, and a `warn` toast with `errors.tooManyAttempts` on a 429 (chapaja-207). Four new specs; the resend spec now waits out the cooldown.

## 1.2.2 — 2026-09-16

### Fixed

- The subtitle wraps with `wrap-anywhere` instead of `break-all`, so a long address still breaks but ordinary words do not.

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

## 1.2.1 — 2026-09-15

### Fixed

- Subtitle with the signup e-mail uses `break-all` so a long address no longer causes
  horizontal scroll at 360×800.

## 1.2.0 — 2026-09-14

### Added

- An account parked for approval is not stored or sent home; the screen says an administrator will approve it and goes to login.

## 1.1.0 — 2026-09-09

### Changed

- OTP input enforces `:maxlength="6"` so the field cannot accept more digits than the code has.

## 1.0.0 — 2026-09-05

### Added

- The screen where a six-digit code is exchanged for an account, with re-send.
