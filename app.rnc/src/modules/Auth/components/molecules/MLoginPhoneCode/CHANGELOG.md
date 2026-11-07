# Changelog — MLoginPhoneCode

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-09-16

### Fixed

- The resend countdown is `useCountdown`: a deadline read against `Date.now()` on each tick and on `visibilitychange`, so a phone that suspended the timers frees the resend on time (largatudoevai-088). New spec for the suspended case.

## 1.0.1 — 2026-09-16

### Fixed

- The resend countdown: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.0.0 — 2026-09-16

### Added

- The code step of the phone-code sign-in, from largatudoevai's WhatsApp code step: `InputOtp` with `one-time-code`, submit on the sixth digit, resend countdown, refusals by stable code (`code_locked` hands the burned code up to the view). Covered by `MLoginPhoneCode.spec.ts`.
