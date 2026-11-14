# Changelog — Auth ForgotPassword Views

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.3 — 2026-11-14

### Changed

- The tile is `MFirmLogo`, the host firm's (rnc-4 F2c2, R15).

## 1.1.2 — 2026-09-16

### Fixed

- **One root node.** The view sat beside a sibling (the back `RouterLink`), which makes it fragment-rooted; App.vue's `out-in` Transition cannot drive that child, so leaving this screen left the app blank until a reload — for every destination, signed in or out. Guarded by `src/test/routedViewSingleRoot.spec.ts`.

## 1.1.1 — 2026-09-14

### Fixed

- `autocomplete` reaches the password `<input>` through `:inputProps`. On `Password` a bare `autocomplete` lands on the wrapper `<div>`, where no password manager looks (from lifeshifter).

## 1.1.0 — 2026-09-14

### Fixed

- `ResetPasswordCodeView` sends the OTP from a local ref mirrored into the Form; PrimeVue Forms could keep only the last digit (primevue#7025) (from chapaja).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
