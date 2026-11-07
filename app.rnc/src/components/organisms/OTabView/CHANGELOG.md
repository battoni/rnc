# Changelog — OTabView / OTabPanel

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.0 — 2026-09-15

### Added

- `tipKey` on `OTabPanel`: an `ATip` beside the tab's title, so a view explains a tab without mounting anything of its own. Read in camelCase or kebab-case, since a panel's props are never normalised (from new.impressao).

### Fixed

- The strip scrolls instead of widening the page: `Tabs` is `scrollable`, and the tree down to `TabList` carries `min-w-0 max-w-full` (from new.impressao). The label size stays `text-xl`; the child's `text-lg` was its own design.
- A click or key press on the tip stays on the tip: `Tab` is a button that switches on click, and the tip is a button inside it, so opening a tip on another tab also switched to it (found by this round's independent review).

## 1.1.0 — 2026-06-05

### Added

- Unit tests (`OTabView.spec.ts`) covering view mode (titles, icon, actions slot, default/active content, model updates) and routes mode (RouterView rendering, route navigation).

## 1.0.0 — 2026-06-05

### Added

- Initial `OTabView` + `OTabPanel` organism. Compound, descriptor-based tabs with `view` and `routes` modes, per-tab title/icon, `#actions` slot, and `v-model` via a writable `computed`.
