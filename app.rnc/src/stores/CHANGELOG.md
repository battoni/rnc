# Changelog — src/stores/

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.5.0 — 2026-09-17

Metadata catching up with code that shipped without it.

### Added

- `tip.store.ts` (`'tip'`, exported as `useTipStore`, 2026-09-04): the active tips, loaded once per session on the first `<ATip>` that needs them, and the anchor the global edit dialog is open for.
- `ui.store.ts` rail state, persisted (2026-09-05): `isNavCollapsed` (`app.rnc:nav-collapsed`), `isMenuButtonPinned` (`app.rnc:menu-button-pinned`) and `expandedNavGroups` (`app.rnc:nav-expanded-groups`), with `toggleNavCollapsed`, `toggleMenuButtonPinned` and `toggleNavGroup`.

### Changed

- `RULES.md` lists the three stores and documents the rail keys and the invariants read back from storage (collapsed without pinned opens; one expanded group at a time).

## 1.4.2 — 2026-07-15

### Added

- Coverage for `setTutorEnabled(false)` dismissing an open contextual tip.

## 1.4.1 — 2026-07-13

### Changed

- Align `ui.store.init()` with approved reference: applies `data-tutor` only (state hydrated in `state()`).

## 1.4.0 — 2026-07-13

### Added

- `ui.store` fullscreen and tutor preferences with localStorage persistence and `data-tutor` application.
- `ui.store.init()` for boot-time preference restore.

## 1.2.0 — 2026-06-13

### Added

- colorScheme store driving runtime light/dark scheme selection (system default, persisted).

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`ui.store.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
