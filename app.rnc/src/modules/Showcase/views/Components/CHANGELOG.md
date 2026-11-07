# Changelog — ComponentsView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.2 — 2026-09-17

### Fixed

- The Select, MultiSelect and Password examples took `id`, which lands on the wrapper `<div>`; they take `inputId`, so each `<label for>` reaches its control (lifeshifter-471). A reference page must model the right prop.

## 1.3.1 — 2026-09-17

### Changed

- The inventory sample is office stock (notebooks, USB cables, office chairs, ballpoint pens in office supplies / electronics / furniture) instead of a print shop's (coated paper, vinyl banner, toner, "A4 Sulfite" — which was Portuguese in the English locale). The quantity formatter is `units.items` ("{count} items" / "{count} itens"); `units.sheets` is gone.

## 1.3.0 — 2026-09-16

### Added

- Sections for `MAddressFields` and `MLocalityFields` (with `showRegion`), with a one-line
  description each, in `en` and `pt-BR`.

## 1.2.1 — 2026-09-16

### Fixed

- The sample table headers and dates: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.2.0 — 2026-09-16

### Changed

- The gallery speaks the language it documents: section titles, button/tag/message samples, tab titles, dialog and toast copy are i18n keys under `showcase.components.*` (en + pt-BR). It had no `$t()` call before (from lifeshifter).
- Sample data is translated too. Rows carry a key for every word a person reads (status, type, priority, stock, item name) and computeds translate them; the badge icon maps are keyed by the translated word, because the `OList*` family looks icons up by the printed value. Brand names, people, SKUs, dates and amounts stay literal, and so do the PrimeVue component names used as field labels.
- The inventory formatter printed `folhas` in every locale; it is now `showcase.components.units.sheets`. The two Portuguese sample entities became `Globex` and `Initech`, names that need no translation.
- The static lists are `UPPER_SNAKE` constants, and the kanban's `class` + `:class` pairs are one `:class` array.

## 1.1.1 — 2026-09-14

### Changed

- Gated on the `design.read` permission through `meta.permission`, enforced by the global auth guard, instead of a `beforeEnter` comparing the role slug with the administrator's. A role check cannot be granted from the permissions tab. `modules/Showcase/guards.ts` is gone (from lifeshifter).

### Fixed

- The overlay `<aside>` is `contents`, so it takes no room in the column's gap (`celer-07`).

## 1.1.0 — 2026-06-13

### Changed

- Remove the unused ThePageHeader icon.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
