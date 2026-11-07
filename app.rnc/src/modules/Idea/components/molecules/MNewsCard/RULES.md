---
version: 1.1.4
origin: pendulum
based-on: 1.1.4
---

# RULES — MNewsCard

> For AI agents. Last updated: 2026-09-17.

One Novidades entry as it appears in the list, inside the month's grid.

|       |                                                                     |
| ----- | ------------------------------------------------------------------- |
| Props | `canManage`, `entry`, `isPublishing`, `reactionsDisabled?`          |
| Emits | `onDelete`, `onEdit`, `onOpen`, `onPublish`, `onReact`, `onReaders` |

## The applause is a SIBLING of the open button, never a child (CRITICAL)

The cover and the headline are one big `<button>`, because a cover and a headline that are not
clickable are a headline somebody hunts for a link beside. The applause therefore **cannot** live
inside that button: `<button>` inside `<button>` is invalid HTML, the browser silently unnests it,
and the clap ends up outside the card opening the article instead of clapping.

The footer — applause, read count, `⋮` — is a sibling of the open button. Keep it one.

## A dead cover costs the image box and nothing else

`alt` is EMPTY and the box is dropped on `error`. A cover that 404s used to render its `alt` — the
entry's title — as bare text sprawling outside the card, which is the shape the owner called
"simplesmente ruim". The headline is already there in text two elements below, so an alt would be
a second copy of it even when the image loads.

A new `cover.url` resets the flag: the previous URL failing says nothing about this one.

## Actions live behind one `⋮`

Three controls in a row on every card, on every row, competed with the writing they sat under.
The pattern is `ThePageHeader`'s: a `Popover`, opened by an ellipsis button, holding the real
buttons.

- **The read count is NOT in the menu.** It is information, not an action.
- **The delete confirmation anchors to the menu ROW that asked for it, and the menu stays open
  behind it.** A `ConfirmPopup` pointed at a removed element renders in the page's top-left corner,
  so the delete row does not hide the menu (edit and publish do). **Retired:** "anchors to the `⋮`,
  captured in `overflowTarget`" — the code had already moved to the row, and that ref was written
  and never read.
- **Apagar sits below a rule, `gap-2` + `mt-1` + `pt-3` away from Editar** — measured at 25px on a
  390px phone, against 8px between the two harmless rows. Adjacent thumb targets need 12px
  (`celer-06-design-system`), and it matters most where one of the pair is destructive.
- **The management row is always its own footer line** (`w-full basis-full justify-between
gap-3`). Applause stays on the first line; eye left and `⋮` right on the second — at every
  width, not only when wrap happens to drop them. Both controls grow their hit area by 6px a side
  under `pointer: coarse`, so 4px between them left the areas overlapping by 8px with the LATER
  sibling winning: a thumb aimed at the audience opened the actions menu.
- **The read count carries `touch-target`.** It is hand-written, and only `.p-button-sm` gets the
  thumb sizing for free.

## No `overflow-hidden` on the card

The cover rounds its own top corners with `rounded-t-lg`. `overflow-hidden` on the article would
clip the applause confetti at the footer's edge, which is the whole gesture the footer exists for.
