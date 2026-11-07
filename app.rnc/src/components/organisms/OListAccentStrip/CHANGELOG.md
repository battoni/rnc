# Changelog — OListAccentStrip

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.2 — 2026-09-21

### Fixed

- The detail dialog is footerless per ITEM. `isFooterless` read `!!slots.actions`, which is true
  even when every button in the slot failed its own `v-if` for that row — so the eyeball opened on
  a footer with nothing in it. It now asks what the slot actually drew for the selected item
  (`hasSlotContent`). Covered in the spec, both directions.

## 1.3.1 — 2026-09-17

### Fixed

- The row actions (pencil, inbox, bin, eye) have an `aria-label` and grow to 44px under a finger
  (`pointer-coarse:h-11 pointer-coarse:w-11`); the strip holding them is `gap-3` instead of
  `gap-1`, so the bin no longer wins the overlap. Covered by `organisms/rowActions.spec.ts`.
- The RULES line about `bg-surface-50` rows now says `bg-panel`, which is what the row paints.

## 1.3.0 — 2026-09-14

### Added

- `emptyMessage` — an i18n key, `common.noItems` by default — rendered through `AEmptyState`, so an empty list fades in with the rest of the app and speaks in the same voice as every other empty state instead of a line of local markup (from lifeshifter).

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
- Unit tests (`OListAccentStrip.spec.ts`) covering the new contract.

### Changed

- `amount` is now optional (was required). Existing financial usage is unchanged.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
