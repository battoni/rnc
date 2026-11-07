# Changelog — AEmptyState

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.2 — 2026-09-16

### Fixed

- The glyph line is text, so `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.1.1 — 2026-09-14

### Changed

- `rounded-lg`, not `rounded-xl`: a card or a list is `lg` on the radius scale; `xl` is for a modal or a full-bleed container (`celer-06`). Found by lifeshifter's radius audit.

## 1.1.0 — 2026-09-06

### Changed

- The tile falls back to `pi pi-inbox` instead of `<ABrandMark>`. The mark's accessible name is a
  person's, so every icon-less empty state announced it to a screen reader — in this bootstrap and
  in every product generated from it. Found in a QA weeks after it existed, which is how a wrong
  default usually surfaces: nothing fails, it simply says the wrong thing.
- Removes the only place a `MERGE` unit reached into a `LOCAL` one. This component travels to every
  project on sync; `ABrandMark` never leaves. A dependency in that direction cannot survive the
  trip, and did not need to exist.

## 1.0.0 — 2026-06-13

### Added

- Branded empty-state atom (bracketed tile, `>_` glyph, title/description, action slot) + unit tests.
