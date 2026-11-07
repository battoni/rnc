# Changelog — Showcase Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.0 — 2026-09-17

### Added

- Module-level `RULES.md` and `CHANGELOG.md` (lifeshifter-343). The module had them only on its
  views. Records the fixture-only rule, the `design.read` gate and the generated feature catalogue.

### Fixed

- `MShowcaseShortForm` and `MShowcaseLongForm` footer buttons `gap-2` → `gap-3` (VIS-15); `ComponentsView` select, multiselect and password take `inputId` (lifeshifter-471). Guarded by `src/test/moduleFooterGap.spec.ts` and `src/test/primevueInputProps.spec.ts`.
- `ShowcaseView` (1.2.2): Known Gap for the title clipped at 390 (VIS-19), doc only.
- `BrandView` (1.1.2): two swatches with both `class` and `:class` take one `:class` array (CEL-16).
- `MShowcaseShortForm` and `MShowcaseLongForm`: the dialog's gap filler (`absolute bottom-0 w-full`) had no `left`, so it sat at its static position 20px in and ended 20px past the sheet; on a phone the page grew to 410px, the browser zoomed out, and the dialog covered 370 of 390px with a strip of page on the right. It takes `left-0` (`src/test/dialogGapFiller.spec.ts`).
