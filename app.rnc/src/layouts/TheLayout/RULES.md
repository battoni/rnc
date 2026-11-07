---
version: 1.3.3
origin: pendulum
based-on: 1.3.3
---

# RULES — TheLayout

> For AI agents. Last updated: 2026-09-15.

## Purpose

Main authenticated application layout. Composes `TheNavbar` with a scrollable content area and an optional page header slot.

## Intentional Decisions

- **`transform-[translateZ(0)]` on `<main>`**: creates a CSS containing block for `position: fixed` descendants. This is required for `MMainDialog`'s `appendTo="self"` to scope the dialog mask inside `<main>` on desktop. Do not remove it.
- **No `overflow-x-hidden` on the root**: setting `overflow-x: hidden` forces `overflow-y` to `auto` (CSS overflow pairing). That invents a second scroll container on the `h-full` root, which then shows a thin vertical bar that eats width and creates the matching horizontal bar — both directions, cutting a few pixels. Clip horizontal overflow at the source (e.g. Navbar `lg:`-only negative margins), not here.
- **`main-scroll` class on the inner scroll div**: `MMainDialog`'s global CSS uses `main:has(.main-dialog-root) .main-scroll { overflow: hidden }` to lock scroll when a dialog is open. The class name is part of that contract — do not rename it.
- **`useUiStore()` drives fullscreen layout**: when `isFullscreen` is true, outer and inner max-width constraints are removed and horizontal padding tightens.
- **`useUiStore()` is called but not destructured**: this initializes the store on layout mount. The store is consumed by child components (`TheNavbar`, `ThePageHeader`). Do not remove the call.
- **`pb-[calc(5.75rem+env(safe-area-inset-bottom,0))]`** on the scroll div: bottom padding accounts for the mobile bottom navbar height (≈4.75rem) plus iOS safe area. On `md+` this resets to `pb-8`.
- **`max-h-[calc(120vh-10rem)]`** on the content wrapper: constrains the scrollable area. Intentional — adjusting this breaks the contained scroll behavior.
- **`pageHeader` slot**: renders above `<main>` with `px-8 pt-8 pb-2` padding. Use this slot with `ThePageHeader`; do not add a page header inside the default slot.

## Prop & Emit Contract

- `classes`: additional Tailwind classes applied to `<main>`. Use for per-page overrides (e.g. disabling overflow).
- `#pageHeader` slot: for `ThePageHeader`. Rendered outside the scroll container so it stays fixed at the top.
- `#default` slot: page content. Rendered inside the scrollable `main-scroll` div.

## Dependencies & Context

- **`MMainDialog`**: depends on `transform-[translateZ(0)]` on `<main>` and the `main-scroll` class. Changing either breaks dialog scoping and scroll lock.
- **`TheNavbar`**: auto-imported, always rendered. This layout is only for authenticated routes.
- **Module overlays mounted here, once, for the whole app**: `OIdeaCaptureDialog` (Idea) and
  `OTipEditDialog` (Tip). Both are opened from a store, from anywhere — an idea from the navbar, a
  tip from whatever screen renders the `<ATip>` — so neither can live inside the screen that
  triggers it. **This is a real dependency in the wrong direction: a module's organism mounted by
  a layout.** It is recorded here because nothing else records it — a missing mount renders
  nothing and throws nothing, and the button that opens it simply does nothing.

## Do Not

- **Do not remove `transform-[translateZ(0)]`** from `<main>` — dialog scoping breaks.
- **Do not put `flex-1 min-h-0` on a view's root** unless that root contains its own scroller. Inside the scrolling flex column `min-h-0` shrinks the box BELOW its content, and the excess overflows visibly WITHOUT joining the scrollable area — the page cannot be scrolled to it, and no bottom padding on `main-scroll` reaches it. A screen that scrolls the page uses `flex flex-1 flex-col`.
- **Do not rename `main-scroll`** — `MMainDialog` CSS targets it by class name.
- **Do not add page headers inside the default slot** — use the `#pageHeader` slot.
- **Do not mount a project's own overlay in this file** — use `TheLocalOverlays.local.vue`, which the sync never touches. Editing `TheLayout.vue` for it conflicts on every sync.
- **Do not delete the overlay mounts as "unused markup"** — they take no props and emit nothing, so
  they look inert. Porting a module and forgetting its mount is the same bug from the other side.
