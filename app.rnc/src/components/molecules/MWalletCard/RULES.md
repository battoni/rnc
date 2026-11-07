---
version: 1.1.2
origin: pendulum
based-on: 1.1.2
---

# RULES — MWalletCard

> For AI agents. Last updated: 2026-09-17.

## Purpose

A metric/stat card for financial dashboards that presents differently across breakpoints: full card (mobile + desktop), compact card with popover (tablet only).

## Intentional Decisions

- **Three rendering states, not two**: the component renders two `Card` elements and one `Popover`. This is intentional:
  - `< md` (mobile) and `lg+` (desktop): first card, full layout with title + description + value.
  - `md` only (tablet, 768–1023px): second card, compact with title + value; description hidden behind a popover triggered by click/keyboard.
  - Achieved with `md:hidden lg:block` on the first card and `hidden md:block lg:hidden` on the second.
- **`popoverReference` is a `ref()` used as a template ref**: it holds the PrimeVue `Popover` instance to call `.toggle()` and `.hide()` programmatically. Do not rename it without updating all usages.
- **Keyboard accessibility on tablet card**: `onTriggerKeydown` handles `Enter` and `Space` to open the popover on the tablet-only card, which has `role="button"` and `tabindex="0"`.
- **Accent stripe is a child `<span>`**, not a border — it uses `absolute` positioning inside an `overflow-hidden` parent to create a left-side color bar.
- **`accentByVariant` and `titleClassByVariant` are non-reactive `const` maps**: they never change at runtime.

## Prop & Emit Contract

- `variant`: drives the left accent stripe color and title text color. Defaults to `'info'`. Must be one of `'info' | 'primary' | 'success' | 'warning'`.
- `title`: the metric label (e.g. "Total Revenue"). Rendered as an `<h3>`.
- `value`: the formatted metric value (e.g. "R$ 42,000"). Rendered prominently; no internal formatting.
- `description`: supporting text shown in full on mobile/desktop and in the popover on tablet.

## Edge Cases Handled

- **Popover close on `@hide`**: PrimeVue fires `hide` when the popover closes for any reason (click outside, scroll). `closePopover` syncs `isPopoverVisible` back to `false` to keep state consistent.

## Do Not

- **Do not add a fourth breakpoint variant** without updating both Card elements and the Popover.
- **Do not format `value` inside this component** — pass a pre-formatted string from the parent.
- **Do not use `text-lime-*`, `text-gray-*`**, or any raw Tailwind palette for variant colors — always extend `accentByVariant`/`titleClassByVariant` with semantic tokens (`bg-*-300/500`, `text-*-700`).
