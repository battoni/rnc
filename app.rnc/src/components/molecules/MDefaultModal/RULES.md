---
version: 1.1.2
origin: pendulum
based-on: 1.1.2
---

# RULES — MDefaultModal

> For AI agents. Last updated: 2026-09-17.

## Purpose

A generic modal container built on PrimeVue `Drawer` that supports all positions (center, top, bottom, left, right) with consistent layout and theming. Provides no built-in title or action buttons — use `MMainDialog` for action sheets with Cancel/Submit.

## Intentional Decisions

- **Uses `Drawer`, not `Dialog`**: Drawer provides enter/exit slide animations for all positions. Dialog's animation is scale/fade only.
- **`position='center'` is remapped to Drawer's `'bottom'`**: Drawer has no native center position, so `drawerPosition` computed maps `'center'` to `'bottom'`, then `wrapperClass` centers the panel visually using flex. Do not read `position` for rendering logic — always use `drawerPosition`.
- **`closeOnMaskClick` maps to Drawer's `dismissable`**: the prop name is intentionally different from PrimeVue's to be more descriptive.
- **`widthClass` controls sizing**: for left/right positions it sets width; for top/bottom it sets width of the centered panel. Default is `'w-[40rem] max-w-[95vw]'`.
- **No title, no footer, no action buttons**: this is a raw container. Use slots (`#default`, `#header`, `#footer`) for all content.
- **`closable: true` default renders a close Button in the header area**: when `closable` is `false` the header area is hidden entirely unless `$slots.header` is present. The close Button is icon-only and carries `:aria-label="$t('common.actions.close')"`.

## Prop & Emit Contract

- `v-model:visible`: controls open/close state. Component calls `closeCallback` internally when the close button or mask is clicked.
- `position`: the logical position. `'center'` renders a floating panel; others render edge-anchored panels.
- `widthClass`: Tailwind width class(es) applied to the panel. For `top`/`bottom`, the panel is always `w-full`; `widthClass` adds to that.
- `contentClass`: applied to the inner content div around `#default` slot.
- `modalClass`: applied to the Drawer root alongside computed position classes.

## Do Not

- **Do not use for add/edit action sheets** — use `MMainDialog` instead (it has Cancel/Submit, title, i18n keys, and bottom-sheet animation).
- **Do not rely on `position` for rendering logic** — use the computed `drawerPosition` internally.
