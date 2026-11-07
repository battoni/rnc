# Changelog — OListDivided

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.7.0 — 2026-09-21

### Fixed

- The ⋮, the inline action strip and the detail dialog's footer are decided per ROW, from what the
  `#actions` slot actually drew for that item, instead of from `!!slots.actions` for the whole list.
  A slot whose every button failed its own `v-if` — the archive hidden on the viewer's own row, the
  delete only on an inactive account — lit a ⋮ that opened empty. Finishes 20cf2fcb, which removed
  the same hole for a viewer with no row actions at all. Four spec cases, two of them red against
  the old decision.

## 1.6.4 — 2026-09-17

### Fixed

- The wide action strip (pencil, inbox, bin, eye) grows to 44px under a finger, like the narrow ⋮
  pair already did. A tablet is touched and is wide enough for the strip. Covered by
  `organisms/rowActions.spec.ts`.
- `RULES.md`: the `valueFormatter` example is `20 items`, not a print shop's `20 folhas`.

## 1.6.3 — 2026-09-16

### Fixed

- The row subtitle: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.6.2 — 2026-09-15

### Fixed

- ⋮ menu and detail-footer secondary group use `gap-3` (was `gap-1` / `gap-2`). Both sit next to
  destructive actions; the strip rule already required 12px, and these two containers missed it.

## 1.6.1 — 2026-09-15

### Fixed

- Detail dialog footer: secondary actions (`#actions` / archive / delete) on the left, Edit on the
  right (`justify-between`). Edit used to lead the right-aligned group, so it sat left of
  destructive actions in Usuários and Dicas.

## 1.6.0 — 2026-09-14

### Added

- `emptyMessage` — an i18n key, `common.noItems` by default — rendered through `AEmptyState`, so an empty list fades in with the rest of the app and speaks in the same voice as every other empty state instead of a line of local markup (from lifeshifter).

### Changed

- `rounded-lg`, not `rounded-xl`: a card or a list is `lg` on the radius scale; `xl` is for a modal or a full-bleed container (`celer-06`). Found by lifeshifter's radius audit.

### Fixed

- The popover's `<aside>` is `contents`. The organism renders two roots, so the aside became a flex child of whatever column held the list, and a `gap` there reserved space for an element that draws nothing (from lifeshifter).

## 1.5.0 — 2026-09-14

### Added

- Container-driven layout: the row folds by the list's width (`@container`), not the viewport's (from new.impressao).
- Below `@md` the organism folds its actions into a ⋮ Popover; `#actions` receives `variant: 'inline' | 'menu'` (from chapaja).
- `subtitle` is rendered under the title (from chapaja and new.impressao).
- `contrast` tone; `aria-label` on every icon-only control; the narrow controls grow to 44px under a coarse pointer.

### Fixed

- The empty state read "No items" in every locale; it reads `common.noItems`.

## 1.4.0 — 2026-09-09

### Fixed

- Action strip spacing is `gap-3` (12px), not `gap-1` (4px). The strip ends in a delete button and the targets sat closer than a fingertip is wide. The `mr-*` on the amount and status tag are gone with it — they only existed to buy back space the gap was not giving.

## 1.3.0 — 2026-09-09

### Changed

- Below `sm`, each row stacks title above actions so narrow viewports keep a readable title.

## 1.2.0 — 2026-06-05

### Added

- Generic optional `amount` (`valueFormatter`/`align`), read-only `#details="{ item }"` eyeball slot, inline gated actions (`canEdit`/`canArchive`/`canDelete` + `@onEdit`/`@onArchive`/`@onDelete`), and a custom `#actions="{ item }"` slot — all mirrored into the detail dialog footer.
- Expanded unit tests covering the new contract (no-amount, formatter, `#details`, `can*` gating, action emits, dialog mirror + close).

### Changed

- `amount` is now optional (was required). Existing financial usage is unchanged.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`OListDivided.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
