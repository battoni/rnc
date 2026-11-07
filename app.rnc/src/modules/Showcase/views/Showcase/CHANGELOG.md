# Changelog — ShowcaseView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.3 — 2026-09-17

### Fixed

- The form contract's gap filler lacked `left-0`: copied as written, it runs past the sheet by the content padding and, on a phone, widened the page and zoomed the dialog out to 370 of 390px. The rule and the example carry `left-0` now. Doc only.

## 1.2.2 — 2026-09-17

### Changed

- `RULES.md` records a Known Gap: at 390 the two labelled header actions leave the title 39px and it is clipped to ">\_ V" (VIS-19). The fix changes the convention this view sets, so it is an open decision. Doc only.

## 1.2.1 — 2026-09-17

### Fixed

- `RULES.md` contradicted the code and celer-06: the form contract required `bg-surface-0` on the sticky footer and gap filler (every form uses `bg-canvas`), the footer example used `gap-2` (under the 12px floor), and the parent example showed `<aside>` without `contents` (rnc-232, lifeshifter-348, lifeshifter-311). Doc only.

## 1.2.0 — 2026-09-16

### Changed

- The screen speaks the active locale: page title, header actions, search placeholder, sort options and dialog titles are i18n keys under `showcase.showcase.*` (en + pt-BR), and `title` now receives a key instead of an English string that vue-i18n echoed back. The two dialog forms it opens (`MShowcaseShortForm`, `MShowcaseLongForm`) moved with it: labels, placeholders, Yup messages and toasts are keys under `showcase.forms.*`, and Cancel/Save reuse `common.cancel`/`common.save`. E-mail, URL and LinkedIn placeholders stay literal — they are format examples (from lifeshifter).

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
