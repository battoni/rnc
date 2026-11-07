---
version: 1.0.0
origin: pendulum
based-on: 1.0.0
---

# RULES — ANavDot

> For AI agents. Last updated: 2026-09-17.

## Purpose

The attention dot beside a navigation entry: something behind it has not been seen yet. Used by
`ONavbarDesktop` and `ONavbarDrawer` on entries whose `hasDot` is set — in this bootstrap, News while something is unread (`navBadges.local.ts`).

## Intentional Decisions

- **A dot, never a number.** A count reads as a queue to clear; the dot marks something worth
  reading. Do not add a `count` prop.
- **The halo breathes, it does not blink.** A static 8px dot in a long rail is easy to miss, so a
  halo scales out and fades on a 2.4s loop. The keyframes live in the component's scoped style.
- **Reduced motion is absence.** Under `prefers-reduced-motion: reduce` the halo animation is
  removed outright; the solid dot alone carries the whole message (celer-09's rule for marks,
  applied to this one).
- **`role="status"` with the `label` as its accessible name.** The navigation passes
  `$t('navigation.unread')`, so a screen reader hears that the entry has something unread.
- **`bg-primary-500` on both circles.** The dot is a mark on the canvas, not text; it does not
  flip with the scheme.

## Prop & Emit Contract

- `label?: string` — an already translated string, used as the `aria-label`. Always pass one: an
  unnamed `role="status"` announces nothing.
- No emits, no slots. The atom does not size itself beyond its own `size-2`.

## Do Not

- **Do not make the dot clickable** — the entry it sits in is the control.
- **Do not replace the scoped keyframes with a Tailwind `animate-ping`** — `ping` is a blink, and
  it is not removed under reduced motion.
