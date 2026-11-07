# Changelog — BrandView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.2 — 2026-09-17

### Fixed

- The ramp swatches and the semantic-token swatches carried both `class` and `:class`; each is one `:class` array now (celer-04, CEL-16). `src/components/unifiedClassBinding.spec.ts` scans module screens too and guards it. Two captions that hugged their closing tag take the multiline shape. No visual change.

## 1.1.1 — 2026-09-16

### Fixed

- Every eyebrow, ramp caption, hex and token note: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).
- Three captions (hex values and token names) were 10px, under the 12px floor (`celer-06`); they are `text-xs`.
- The hero lede named a person and the theme (`showcase.brand.hero.lede`). The page is LOCAL but its strings live in the synced core locales since the i18n sweep, so the sync's brand audit flagged it; the sentence now describes whichever theme is active.

## 1.1.0 — 2026-09-16

### Changed

- The gallery speaks the language it documents: every heading, eyebrow, ramp and token note, caption, sample control and glossary line is an i18n key under `showcase.brand.*` (en + pt-BR). The glossary sentence uses `<i18n-t>` so the underlined term stays an element inside a translated sentence. Token names, hex values, font names and the code glyphs stay literal (from lifeshifter).
- The static lists are `UPPER_SNAKE` constants and carry keys, not prose.

## 1.0.1 — 2026-09-14

### Changed

- Gated on the `design.read` permission through `meta.permission`, enforced by the global auth guard, instead of a `beforeEnter` comparing the role slug with the administrator's. A role check cannot be granted from the permissions tab. `modules/Showcase/guards.ts` is gone (from lifeshifter).

## 1.0.0 — 2026-06-13

### Added

- Initial brand reference view: monogram, signature, pattern, empty-state, and theme showcase.
