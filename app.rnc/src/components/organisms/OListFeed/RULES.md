---
version: 1.3.3
origin: pendulum
based-on: 1.3.3
---

# RULES — OListFeed

> For AI agents. Last updated: 2026-09-21.

## Purpose

A generic activity-feed organism: rows with a colored icon circle (driven by badge tone), a two-line center text block, and a right column with amount and status. Used for **read-only timelines** (e.g. version history) as well as managed lists. Works for any data shape — the parent maps data keys to visual roles via props.

## Props Contract

| Prop                                   | Required | Description                                                                                                                                                                                                               |
| -------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `items`                                | ✓        | Array of plain objects — any shape                                                                                                                                                                                        |
| `title`                                | ✓        | `{ valueKey }` — primary text (center, top)                                                                                                                                                                               |
| `amount`                               | —        | **Optional.** `{ valueKey, valueFormatter?, align? }`. When omitted (e.g. a pure timeline) the row renders cleanly. `valueFormatter(raw) => string` formats non-money values; `align: 'left' \| 'right'` (default right). |
| `badge`                                | —        | `{ valueKey, toneKey }` — tone drives the icon-circle color; value appears in the subtitle line                                                                                                                           |
| `badgeIconMap`                         | —        | Maps badge value → feed icon (`pi` class); default `pi pi-circle`                                                                                                                                                         |
| `status`                               | —        | `{ valueKey, toneKey }` — right status tag                                                                                                                                                                                |
| `statusIconMap`                        | —        | Maps status tone value → `pi` icon class                                                                                                                                                                                  |
| `subtitle`                             | —        | `{ valueKey }` — muted text after the badge value                                                                                                                                                                         |
| `itemKey`                              | —        | Key field for `v-for`; default `'id'`                                                                                                                                                                                     |
| `showDetails`                          | —        | Eye button per row → opens the detail dialog                                                                                                                                                                              |
| `canEdit` / `canArchive` / `canDelete` | —        | Default `false`. Render the matching gated inline action button (pencil / inbox / trash)                                                                                                                                  |

## Events

- `@onEdit(item)` / `@onArchive(item)` / `@onDelete(item)` — emitted by the gated action buttons (inline **and** from the detail dialog) with the row's item.

## Slots

- `#details="{ item }"` — read-only detail content rendered inside the eyeball `MMainDialog`. Optional; the dialog is empty without it.
- `#actions="{ item, close }"` — custom per-row action buttons. Rendered **both** inline in the row **and** in the detail dialog footer, each with the correct `item`. `close()` dismisses the eyeball dialog — call it from an action triggered there.

## Eyeball dialog

The eye opens `MMainDialog` showing `#details`. **All actions — built-in `can*` buttons and the custom `#actions` slot — are mirrored into the dialog footer.** Built-in actions close the dialog before emitting; custom `#actions` run the parent handler and can call the slot's `close()` to dismiss it. With no actions, the dialog is `isFooterless` (read-only).

## Intentional Decisions

- **`badge` tone drives the icon-circle color** (`iconBgClass`/`iconBgByTone`, internal); `badgeIconMap` picks the icon glyph.
- **`amount` is generic, not a money hero** — optional, with `valueFormatter`/`align`. Read-only timelines typically pass a formatted value or omit it. Financial usage (`{ valueKey }` only) is unchanged (back-compat).
- **Actions mirror inline ↔ dialog** — write a custom action once in `#actions`; it appears both inline and in the eyeball. Built-in `can*` buttons behave the same.
- **The row actions are named and grow under a finger.** Each icon-only button (pencil, inbox, bin, eye) carries an `aria-label` (`common.actions.*`, `common.details`) and `pointer-coarse:h-11 pointer-coarse:w-11`: 28px under a mouse, 44px under a thumb. The strip holding them is `gap-3` — at `gap-1` the bin, the later sibling, won the overlap (celer-06). `rowActions.spec.ts` covers all five list organisms.

## A slot that exists is not a slot that renders

`isFooterless` asks `hasItemActions(selectedItem)`, which renders the `#actions` slot for that item
and inspects the output (`hasSlotContent` in `@Helpers`). `!!slots.actions` is true even when every
button inside failed its own `v-if`, and that gave the eyeball dialog an empty footer.

## Do Not

- **Do not decide whether a row has actions from `!!slots.actions`** — a slot whose buttons each
  failed their own `v-if` renders nothing; ask `hasSlotContent` what it drew for that item.
- **Do not add sorting or filtering** — display only; the parent prepares data (search `MultiSelect` + `MOrderBy` at the view level).
- **Do not hardcode tone/icon values** — drive via `badgeIconMap`/`statusIconMap` and tone keys from the parent.
