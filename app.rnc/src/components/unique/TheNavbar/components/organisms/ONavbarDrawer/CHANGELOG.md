# Changelog — ONavbarDrawer

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.2 — 2026-11-14

### Changed

- The drawer's header shows `MNavbarBrand` instead of a bare `ALogo` tile, kept clear of the close button (rnc-4 F2b, R10).

## 1.3.1 — 2026-09-17

### Fixed

- The container is `bg-canvas`. On `--p-drawer-background` (`#252626` in dark) `text-muted`
  measured 4.43:1 and the `text-subtle` chevron 2.99:1, under their floors; the contrast e2e for
  the mobile drawer is green now. Light is unchanged.
- A group's children are `pointer-coarse:min-h-11`: 44px under a finger, still 40px under a mouse.
- The icon-only close button has an `aria-label` (`common.actions.close`).
- The close button is `pointer-coarse:size-11`: 44px under a finger, 40px under a mouse.
- The two dividers lost `before:border-line`: the preset already paints them `--color-line`.

## 1.3.0 — 2026-09-17

### Added

- `docsLink?: NavExternalItem | null` (shipped 2026-09-16 without its own entry): the documentation portal as an external link (`nav-docs`) after Personalization, in a new tab. Absent or `null` renders nothing.

## 1.2.0 — 2026-09-06

### Added

- Groups render exactly as in `ONavbarDesktop` — the same grid track, the same two row shapes,
  the same 18px child indent. The rail and the drawer are the same navigation at two widths.

### Changed

- Toggling a group does NOT close the drawer; only navigation does. Opening a group to reach its
  child inside a drawer that dismissed itself on the first tap is the bug this prevents.

## 1.1.0 — 2026-07-13

### Added

- Personalization `RouterLink` in the drawer footer.

### Removed

- `AColorSchemeToggle` from the drawer footer.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
