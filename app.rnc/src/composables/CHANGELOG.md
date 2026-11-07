# Changelog — src/composables/

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.6.0 — 2026-09-17

### Added

- `usePaginatedList` exposes `loadFailed` (true after a rejected fetch, reset when the next load starts), and `load()` returns its promise. A view can now tell "did not load" from "empty" (chapaja-247, lifeshifter-350). Two new specs, seen failing first.

## 1.5.1 — 2026-09-17

### Fixed

- `useLocale.setLocale` applies PrimeVue's catalogue too (`applyPrimeVueLocale`), so month and day
  names and the widgets' aria labels follow a language switch without a reload (lifeshifter-428).
  Covered by `useLocale.spec.ts`.

## 1.5.0 — 2026-09-16

### Added

- `brazil/useCepLookup` — the CEP lookup behind `MAddressFields` and `MLocalityFields`: a loading
  flag, a failed flag, and an overtaken lookup that resolves to `null` without touching either.
  Re-exported from the barrel. `useCepLookup.spec.ts` against MSW.

- `useCountdown` — a countdown measured by the wall clock: a stored deadline, re-read on each tick and on `visibilitychange`, so a phone that suspended the timers does not keep a resend locked past its minute (largatudoevai-088). Covered by `useCountdown.spec.ts`.

### Changed

- RULES: a create on a filtered list reloads page 1 instead of `prependItem` (chapaja-224).

## 1.4.0 — 2026-09-16

### Added

- `useLocale` — the single writer of the global language. It switches vue-i18n, persists the choice
  (read back at boot by `libraries/i18n`) and mirrors it on `<html lang>`, which is what a screen
  reader picks its voice from; an unknown value is refused rather than applied (from largatudoevai).
- `useLocaleFromQuery` — `?locale=` on any route, watched `immediate` because the app mounts after
  `router.isReady()` and the route carrying the parameter is already resolved. Called once from
  `App.vue` (from largatudoevai).

## 1.3.0 — 2026-09-14

### Added

- `usePaginatedList` — the server-pagination loop: items, page, total, loading, and `removeItem` / `prependItem` that keep the count and the pager in step (from chapaja).

## 1.2.2 — 2026-07-15

### Fixed

- `resetSingleTipState()` now dismisses the active tip before clearing state, preventing an orphaned popover when tutor mode is disabled while a tip is open.

## 1.2.1 — 2026-07-13

### Changed

- Align `useSingleTip` with approved reference (`ref`-based state, public API via composable only).

## 1.2.0 — 2026-07-13

### Added

- `useSingleTip` composable for single-open contextual tips (`openTip`, `closeTip`, `resetSingleTipState`).
- Unit tests (`useSingleTip.spec.ts`).

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`useGlobalAbortController.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
