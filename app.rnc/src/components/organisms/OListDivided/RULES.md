---
version: 1.7.0
origin: pendulum
based-on: 1.7.0
---

# RULES — OListDivided

> For AI agents. Last updated: 2026-09-21.

## Purpose

A generic divided-list organism: items rendered as horizontal rows inside a rounded bordered container, separated by a bottom border. Reads as a responsive "data table". **Default layout for managed lists.** Works for any data shape — the parent maps data keys to visual roles via props.

## Props Contract

| Prop                                   | Required | Description                                                                                                                                                                                                                              |
| -------------------------------------- | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `items`                                | ✓        | Array of plain objects — any shape                                                                                                                                                                                                       |
| `title`                                | ✓        | `{ valueKey }` — primary text (left, bold)                                                                                                                                                                                               |
| `amount`                               | —        | **Optional.** `{ valueKey, valueFormatter?, align? }`. When omitted the row renders cleanly (title is primary). `valueFormatter(raw) => string` formats non-money values (`R$ 96,05`, `12 min`, `20 items`); `align: 'left' \| 'right'`. |
| `badge`                                | —        | `{ valueKey, toneKey }` — left tag (type/category)                                                                                                                                                                                       |
| `status`                               | —        | `{ valueKey, toneKey }` — right status tag                                                                                                                                                                                               |
| `statusIconMap`                        | —        | Maps status tone value → `pi` icon class                                                                                                                                                                                                 |
| `subtitle`                             | —        | `{ valueKey }` — muted truncating line under the title                                                                                                                                                                                   |
| `itemKey`                              | —        | Key field for `v-for`; default `'id'`                                                                                                                                                                                                    |
| `showDetails`                          | —        | Eye button per row → opens the detail dialog                                                                                                                                                                                             |
| `canEdit` / `canArchive` / `canDelete` | —        | Default `false`. Render the matching gated inline action button (pencil / inbox / trash)                                                                                                                                                 |

## Events

- `@onEdit(item)` / `@onArchive(item)` / `@onDelete(item)` — emitted by the gated action buttons (inline **and** from the detail dialog) with the row's item.

## Slots

- `#details="{ item }"` — read-only detail content rendered inside the eyeball `MMainDialog`. Optional; the dialog is empty without it.
- `#actions="{ item, close, variant }"` — custom per-row action buttons (e.g. a History button). Rendered inline in the row, in the ⋮ menu of a narrow list, and in the detail dialog footer, each with the correct `item`. `variant` is `'inline'` (row, dialog) or `'menu'` (⋮): render an icon-only button inline and a labeled `w-full justify-start` one in the menu. `close()` dismisses the dialog and the menu — call it from an action triggered there.

## Eyeball dialog

The eye opens `MMainDialog` showing `#details`. **All actions — built-in `can*` buttons and the custom `#actions` slot — are mirrored into the dialog footer.** Built-in actions close the dialog before emitting; custom `#actions` run the parent handler and can call the slot's `close()` to dismiss it. With no actions, the dialog is `isFooterless` (read-only).

**Footer order (CRITICAL):** secondary actions (`canArchive` / `canDelete` / `#actions`) sit on the
**left**; **Edit** sits on the **right** (`justify-between`). Edit used to render first; with
`MMainDialog`'s `justify-end` that put the primary action to the left of delete/archive.

## Intentional Decisions

- **`amount` is generic, not a money hero** — optional, with `valueFormatter`/`align` for any value type. Financial usage (`{ valueKey }` only) is unchanged (back-compat).
- **Actions mirror inline ↔ dialog** — write a custom action once in `#actions`; it appears both inline and in the eyeball. Built-in `can*` buttons behave the same.
- **Layout follows the LIST's width, never the screen's** — the wrapper is an `@container`. A list inside a tab or accordion has to fold while a full-width list on the same screen stays a row, and a viewport variant (`md:`, `lg:`) cannot tell them apart. Do not swap these for viewport variants.
- **Three widths.** Below `@md`: title (and subtitle) with the eye and a ⋮ beside it; badge, amount and status on the line below. `@md`–`@2xl`: badge + title, then the meta and the inline action strip. From `@2xl`: one horizontal row.
- **The organism owns the ⋮ menu** (from chapaja's narrow layout, driven by the container instead of an arbitrary 415px). Built-in `can*` actions and `#actions` fold into it; no view builds its own overflow menu. The eye is never in the menu — reading a row is not an action on it.
- **Every row control grows to 44px under a coarse pointer** (`pointer-coarse:h-11 pointer-coarse:w-11`), in the narrow ⋮ pair and in the wide strip alike. A tablet is touched and is wide enough for the strip. _Retired wording:_ "the wide strip keeps the drawn size" — it left pencil, inbox and bin at 28px on exactly the screens the touch rule was written for.
- **Every icon-only control has an `aria-label`**.
- **`tagClassByTone` is internal** — `error`/`warning` alias to `danger`/`warn`.
- **`contrast` tone** — a categorical tag with no state meaning (a type, a role).
- **`bg-canvas` on the wrapper** — role token that flips light↔dark.

## `gap-3` in the action strip is load-bearing (CRITICAL)

The strip ends in a delete button, and it shipped at `gap-1` — 4px between 28px targets, which is
closer than a fingertip is wide. On a touch screen the hit areas overlap and the later sibling
wins, so aiming at the pencil lands on the bin. `06-design-system` sets 12px as the floor and says
"never less where one of them is destructive"; this is the row that rule was written about.

Anything added to the strip inherits that gap. **Do not give a child its own `mr-*` to separate
itself** — the amount and the status tag carried `mr-2` / `mr-1` for exactly that reason, they
stacked on top of the gap once it was correct, and they are gone. The ⋮ and the eye in the
narrow controls keep the same 12px.

## The ⋮ answers for the row, not for the list

`hasItemActions(item, variant)` renders the `#actions` slot for that item and inspects the output
(`hasSlotContent` in `@Helpers`), because a slot whose buttons each failed their own `v-if` renders
only a Comment. The built-in `can*` props are the same on every row, so they answer first.

A parent still gates its own buttons per item — that is the point of the slot — and it no longer
has to gate the slot's PRESENCE to keep the menu honest.

## Do Not

- **Do not decide whether a row has actions from `!!slots.actions`** — a slot whose buttons each
  failed their own `v-if` renders nothing; ask `hasSlotContent` what it drew for that item.
- **Do not route a confirm-popup action through `canArchive`/`canDelete`** — they emit only the item, and a `ConfirmPopup` needs the clicked element as its anchor. Put that action in `#actions`, and pass only `canEdit` (or nothing) so the row does not render the action twice.
- **Do not add sorting or filtering** — display only; the parent prepares data (search `MultiSelect` + `MOrderBy` at the view level).
- **Do not hardcode tone/icon values** — drive via `statusIconMap` and tone keys from the parent.
- **Do not put long labeled buttons in `#actions` on dense lists** — prefer icon-only with `aria-label`; labeled CTAs belong in `#details` or the dialog footer.
