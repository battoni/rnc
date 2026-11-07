# Changelog — OListCardGrid

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.4.1 — 2026-09-21

### Fixed

- The detail dialog is footerless per ITEM. `isFooterless` read `!!slots.actions`, which is true
  even when every button in the slot failed its own `v-if` for that row — so the eyeball opened on
  a footer with nothing in it. It now asks what the slot actually drew for the selected item
  (`hasSlotContent`). Covered in the spec, both directions.

## 1.4.0 — 2026-09-17

### Fixed

- The row actions (pencil, inbox, bin, eye) have an `aria-label` and grow to 44px under a finger
  (`pointer-coarse:h-11 pointer-coarse:w-11`); the strip holding them is `gap-3` instead of
  `gap-1`, so the bin no longer wins the overlap. Covered by `organisms/rowActions.spec.ts`.
- A card is `bg-panel shadow-lift` with no border, instead of the retired flat card
  (`border-line bg-canvas … border`), as celer-06's "Card chrome" asks. The status `Tag` lost its
  `mr-1`, which compensated for the old `gap-1`.

## 1.3.1 — 2026-09-16

### Fixed

- The card subtitle: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.3.0 — 2026-09-14

### Added

- `emptyMessage` — an i18n key, `common.noItems` by default — rendered through `AEmptyState`, so an empty list fades in with the rest of the app and speaks in the same voice as every other empty state instead of a line of local markup (from lifeshifter).

### Changed

- `rounded-lg`, not `rounded-xl`: a card or a list is `lg` on the radius scale; `xl` is for a modal or a full-bleed container (`celer-06`). Found by lifeshifter's radius audit.

### Fixed

- The popover's `<aside>` is `contents`. The organism renders two roots, so the aside became a flex child of whatever column held the list, and a `gap` there reserved space for an element that draws nothing (from lifeshifter).

## 1.2.0 — 2026-09-14

### Added

- `contrast` tone — a categorical tag (type, role, internal label) that does not borrow a state colour (from chapaja).

### Fixed

- The empty state said "No items" in English in every locale; it reads `common.noItems` (from new.impressao).

## 1.1.0 — 2026-06-05

### Added

- Generic optional `amount` (`valueFormatter`/`align`), read-only `#details="{ item }"` eyeball slot, inline gated actions (`canEdit`/`canArchive`/`canDelete` + `@onEdit`/`@onArchive`/`@onDelete`), and a custom `#actions="{ item }"` slot — all mirrored into the detail dialog footer.
- Unit tests (`OListCardGrid.spec.ts`) covering the new contract.

### Changed

- `amount` is now optional (was required). Existing financial usage is unchanged.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
