# Changelog — TermsView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.2 — 2026-09-16

### Fixed

- **One root node.** The view sat beside a sibling (the back `RouterLink`), which makes it fragment-rooted; App.vue's `out-in` Transition cannot drive that child, so leaving this screen left the app blank until a reload — for every destination, signed in or out. Guarded by `src/test/routedViewSingleRoot.spec.ts`.
- Paragraphs use `wrap-anywhere`, not `break-all`: prose must not hyphenate ordinary words mid-letter;
  the long-token overflow is still covered.

## 1.0.1 — 2026-09-15

### Fixed

- Content wrapper uses `w-full px-4`; paragraphs use `break-all`. At 360×800 the text no longer
  sits flush to the viewport edges, and long tokens cannot force horizontal scroll.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
