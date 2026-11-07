# Changelog — ClickGuard

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-09-17

### Changed

- RULES: `AGuardedButton` is named as NOT ported (Known Gaps) instead of as the half of the
  contract that covers returned promises; "in use today" lists `MNewsReactions`, the only
  `data-allow-rapid-click` in rnc, instead of lifeshifter's calendar views. `plugin.ts`
  comment aligned. No behaviour change.

## 1.0.0 — 2026-09-01

### Added

- App-wide double-click guard, installed once as a capture-phase `click` listener on `document`.
  A second click on the same button inside 350ms is killed with `preventDefault()` +
  `stopImmediatePropagation()`, so the handler, every ancestor handler and a form's implicit
  submit all hear nothing. The lock is per element, held in a `WeakMap`, and always has a timer
  behind it — 350ms plain, 8s for a held one — so no path can leave a button dead.
- A hold that follows the app's own busy signal: a click that _raises_ `useAppBusy` keeps its
  button locked until the work drains, rather than for a flat 350ms. Only on a rise, so a leaked
  request counter cannot freeze the whole interface.
- The escape hatch `data-allow-rapid-click`, honoured on the button or any ancestor, plus built-in
  exemptions for the PrimeVue sections that cannot be annotated at the call site (InputNumber
  spinner, DatePicker arrows, Paginator).
- Unit tests (`clickGuard.spec.ts`) — swallowed repeats, the window expiring, per-element
  isolation, ancestors, both escape hatches, the busy hold, and the never-stranded guarantee.
