---
version: 1.2.0
origin: pendulum
based-on: 1.2.0
---

# RULES — ALogo

> For AI agents. Last updated: 2026-05-22.

## Purpose

Renders the app logo as an `<img>` tag, switching between a full-width and a compact minimum variant.

> **The behaviour contract lives in `.claude/rules/celer-09-brand-mark-contract.md`**, not here.
> This file is `LOCAL` — the sync never touches it, which is right for a mark and wrong for
> everything learned about how a mark should behave. Read the contract before adding a prop; this
> file records only how THIS project's mark implements it.

## Intentional Decisions

- **Logo PNGs are colocated in this folder**, not in `src/assets/`. Do not move them — the component imports them by relative path.
- **`variant='full'` is the default**: explicit `withDefaults` keeps the prop optional at the call site.
- **A raster mark, so only `variant` is implemented.** The contract's motion props (`pulse`,
  `loop`, `entrance`, `tone`) are for a mark drawn as inline SVG. Do not add them to this
  component and animate the `<img>` — that moves the picture rather than the mark, and it is the
  wrong shape for the thing being asked for. A project that wants them redraws its mark as SVG
  first, and then the contract is already written.
- **Wrapper `div` is `flex`**: sizing is controlled by the parent (e.g. `max-w-[100px]`), not by the component itself. The `w-full` on the `<img>` fills whatever the parent constrains.

## Prop & Emit Contract

- `variant`: `'full'` renders the horizontal logo; `'min'` renders the icon-only version. No other values are accepted.

## Do Not

- **Do not add hardcoded width/height** on this component — size from the parent container.
- **Do not add dark-mode variants here** without adding the corresponding PNG assets alongside.
