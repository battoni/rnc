# Changelog — Auth Login Views

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-09-16

### Changed

- `LoginPhoneFlowView` is the whole phone sign-in: one `codeRequest` ref switches between `MLoginPhone` and `MLoginPhoneCode`, and the session is stored here with the same call the password forms make. A burned code returns to the filled-in phone step with an info toast. Covered by `LoginPhoneFlowView.spec.ts`.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
