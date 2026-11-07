# Changelog — src/directives/

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-11-07

### Added

- `directiveImports.spec.ts` (rnc-2): every `.vue` using `v-<directive>` imports it; seen red against
  `RadarView`, which used `v-count-up` unimported.

## 1.0.0 — 2026-11-07

### Added

- The unit, with the `@Directives` alias (vite, tsconfig, vitest): `vReveal` (`.stagger`), `vCountUp`,
  `vSpotlight`, `vTilt` and the shared `inView` observer (rnc-1, the site's motion after the demo).
  Each has a spec, seen red first against the state it guards: a reveal that never shows, a roll
  that never clears, a tilt that leans under reduced motion, a spotlight that follows a finger.
