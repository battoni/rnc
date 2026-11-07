# Changelog — ONavbarMobile

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.4.3 — 2026-09-17

### Fixed

- The menu button's comment said `touch-target` collapses a PrimeVue icon-only button to 12×12. That was true while the growth took over `::after`; it is a `::before` now (theme 2.4.x), so the comment gives the reason that still holds — the button is drawn at 44px. No behaviour change.

## 1.4.2 — 2026-09-17

### Fixed

- `gap-3` between the menu, the personalization link and the scroller, and between the links inside
  it (was `gap-1` / `gap-0.5`): the links' 6px touch growth overlapped and the later one won the tap.
- The scroller is `-m-1.5 p-1.5`: `overflow-x-auto` clipped the links' growth above and below, so a
  link was 34px tall to a thumb. Bar height and outer spacing are unchanged.

## 1.4.1 — 2026-09-17

### Changed

- `min-[480px]:` → `xs:`, the breakpoint now declared in `styles/tailwind.css` (`--breakpoint-xs: 30rem`). An arbitrary min-width variant is emitted before the named ones, so a later `sm:` on the same property would win without a word; the named one sorts where it belongs. Same 480px, same layout.

## 1.4.0 — 2026-09-09

### Fixed

- The backdrop wears `canvas-atmosphere` alongside `bg-canvas`. Flat `bg-canvas` painted the colour but not the theme's texture, so a hard-edged black band appeared under the bar wherever the page's pattern stopped.

## 1.3.0 — 2026-09-06

### Changed

- The bar reserves its space through `--mobile-nav-band`, one definition shared with the layout's
  scroller and the backdrop. It used to be spelled out in three places as
  `1rem + env(safe-area-inset-bottom)`, and that sum is the bug: a browser reporting no inset gets
  the 16px float, one reporting an inset STACKS the two, and the bar floats that much too high
  over a band of empty canvas. `max()` is the fix — the float OR the device's reserved area,
  whichever is larger, never both.
- The bar stays flat: it filters the resolved top level only, so a route that exists solely as a
  child of a group never appears here.

## 1.2.0 — 2026-07-13

### Added

- Compact personalization icon link next to the drawer toggle.

## 1.1.0 — 2026-06-13

### Changed

- Migrate chrome to scheme-aware role tokens.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
