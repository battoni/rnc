---
version: 1.3.2
origin: pendulum
based-on: 1.3.2
---

# RULES — OListAccentStrip

> For AI agents. Last updated: 2026-09-21.

## Purpose

A generic accent-strip organism: rows with a colored left border (`border-l-4`) driven by the badge's tone, on a light `bg-surface-50` background. Communicates category at a glance. Works for any data shape — the parent maps data keys to visual roles via props.

## Props Contract

| Prop                                   | Required | Description                                                                                                                                                                                        |
| -------------------------------------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `items`                                | ✓        | Array of plain objects — any shape                                                                                                                                                                 |
| `title`                                | ✓        | `{ valueKey }` — primary text (top-left)                                                                                                                                                           |
| `amount`                               | —        | **Optional.** `{ valueKey, valueFormatter?, align? }`. When omitted the row renders cleanly. `valueFormatter(raw) => string` formats non-money values; `align: 'left' \| 'right'` (default right). |
| `badge`                                | —        | `{ valueKey, toneKey }` — drives the left accent border color AND appears in the subtitle line                                                                                                     |
| `status`                               | —        | `{ valueKey, toneKey }` — right status tag                                                                                                                                                         |
| `statusIconMap`                        | —        | Maps status tone value → `pi` icon class                                                                                                                                                           |
| `subtitle`                             | —        | `{ valueKey }` — muted text after the badge value                                                                                                                                                  |
| `itemKey`                              | —        | Key field for `v-for`; default `'id'`                                                                                                                                                              |
| `showDetails`                          | —        | Eye button per row → opens the detail dialog                                                                                                                                                       |
| `canEdit` / `canArchive` / `canDelete` | —        | Default `false`. Render the matching gated inline action button (pencil / inbox / trash)                                                                                                           |

## Events

- `@onEdit(item)` / `@onArchive(item)` / `@onDelete(item)` — emitted by the gated action buttons (inline **and** from the detail dialog) with the row's item.

## Slots

- `#details="{ item }"` — read-only detail content rendered inside the eyeball `MMainDialog`. Optional; the dialog is empty without it.
- `#actions="{ item, close }"` — custom per-row action buttons. Rendered **both** inline in the row **and** in the detail dialog footer, each with the correct `item`. `close()` dismisses the eyeball dialog — call it from an action triggered there.

## Eyeball dialog

The eye opens `MMainDialog` showing `#details`. **All actions — built-in `can*` buttons and the custom `#actions` slot — are mirrored into the dialog footer.** Built-in actions close the dialog before emitting; custom `#actions` run the parent handler and can call the slot's `close()` to dismiss it. With no actions, the dialog is `isFooterless` (read-only).

## Intentional Decisions

- **`badge` tone drives the left accent border** (`accentBorderClass`/`borderByTone`, internal). Do not accept a border color as a direct prop.
- **`amount` is generic, not a money hero** — optional, with `valueFormatter`/`align`. Financial usage (`{ valueKey }` only) is unchanged (back-compat).
- **Actions mirror inline ↔ dialog** — write a custom action once in `#actions`; it appears both inline and in the eyeball. Built-in `can*` buttons behave the same.
- **`bg-panel` on rows** — the slight offset from the canvas is intentional. _Retired wording:_ "`bg-surface-50` on rows" — the row moved to the role token in the role-token migration and the line was never updated.
- **The row actions are named and grow under a finger.** Each icon-only button (pencil, inbox, bin, eye) carries an `aria-label` (`common.actions.*`, `common.details`) and `pointer-coarse:h-11 pointer-coarse:w-11`: 28px under a mouse, 44px under a thumb. The strip holding them is `gap-3` — at `gap-1` the bin, the later sibling, won the overlap (celer-06). `rowActions.spec.ts` covers all five list organisms.

## A slot that exists is not a slot that renders

`isFooterless` asks `hasItemActions(selectedItem)`, which renders the `#actions` slot for that item
and inspects the output (`hasSlotContent` in `@Helpers`). `!!slots.actions` is true even when every
button inside failed its own `v-if`, and that gave the eyeball dialog an empty footer.

## Do Not

- **Do not decide whether a row has actions from `!!slots.actions`** — a slot whose buttons each
  failed their own `v-if` renders nothing; ask `hasSlotContent` what it drew for that item.
- **Do not override border colors via class** — always use `badge.toneKey` to drive the accent.
- **Do not add sorting or filtering** — display only; the parent prepares data (search `MultiSelect` + `MOrderBy` at the view level).
