# Changelog — ONavbarDesktop

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.4.2 — 2026-11-14

### Changed

- The top of the rail is `MNavbarBrand` (the firm's tile, logo text and `platform.name`) instead of a bare `ALogo` tile (rnc-4 F2b, R10).

## 1.4.1 — 2026-09-17

### Fixed

- A group's children are `pointer-coarse:min-h-11`: 44px under a finger, still 40px under a mouse.
  The RULES line that called 40px enough is retired.
- The two dividers lost `before:border-line`: the preset already paints them `--color-line`.

## 1.4.0 — 2026-09-17

### Added

- `docsLink?: NavExternalItem | null` (shipped 2026-09-16 without its own entry): the documentation portal as an external link (`nav-docs`) after Personalization, in a new tab. Absent or `null` renders nothing.

## 1.3.0 — 2026-09-06

### Added

- Groups: the `0fr → 1fr` grid track, the `inert` collapsed block, the two row shapes decided by
  whether the group declares a route, and the `pl-[18px] text-sm text-muted min-h-10` child.
- An opened group is scrolled into view through `revealNavGroup` when it unfolds past the fold.

### Changed

- The rail stays mounted and goes `inert` when collapsed, instead of being unmounted by the
  parent's `v-if`.
- `-mx-3 px-3` so the card's relief paints inside the `overflow-hidden` clip.
- Only the middle block scrolls: `pt:body` and `pt:content` carry `min-h-0`, so the item list —
  not the rail, not the page — takes the overflow when a group expands.

## 1.2.0 — 2026-07-13

### Added

- Personalization `RouterLink` in the footer section.

### Removed

- `AColorSchemeToggle` from the sidebar footer.

## 1.1.0 — 2026-06-13

### Changed

- Blend the sidenav into the body background (transparent chrome) in both schemes.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
