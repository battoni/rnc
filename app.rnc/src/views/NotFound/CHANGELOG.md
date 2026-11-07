# Changelog — NotFoundView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.3 — 2026-09-16

### Fixed

- **One root node.** The view sat beside a sibling (the explanatory comment), which makes it fragment-rooted; App.vue's `out-in` Transition cannot drive that child, so leaving this screen left the app blank until a reload — for every destination, signed in or out. Guarded by `src/test/routedViewSingleRoot.spec.ts`.
  The named `push` landed in 1.0.1 but the screen stayed blank on leave; this is the actual cause,
  found by the e2e that 1.0.1 shipped red.

## 1.0.2 — 2026-09-15

### Changed

- RULES: Home blank fix is named `push` only. App.vue keeps `mode="out-in"` — dropping it for a
  keyed simultaneous fade invented global page scrollbars.

## 1.0.1 — 2026-09-15

### Fixed

- "Back to start" navigates by route name (`push({ name })`), not `resolve(...).path`. Leaving the
  catch-all via a path string plus App.vue's out-in route-fade left Home blank or invisible until
  a hard reload. App.vue drops out-in and keys the Transition by `route.fullPath`.

## 1.0.0 — 2026-09-14

### Added

- The view and its catch-all route, open to everyone. An unknown address used to render nothing at all (from lifeshifter, where the route was `isPublic` — which sends a signed-in person Home instead; here it is `isOpen`).
