---
version: 1.0.0
origin: pendulum
based-on: 1.0.0
---

# RULES — ABrandMark

> For AI agents.

## Purpose

Renders the Guilherme Battoni "G" monogram as an inline SVG using `currentColor`, so callers control the colour with a text utility (e.g. ink on light, brand green on dark).

## Intentional Decisions

- **Single `<path>`, `fill="currentColor"`**: the mark inherits the parent text colour. Never hard-code a fill here.
- **Size from the parent**: pass `h-*` / `w-auto` at the call site; the SVG has no intrinsic size beyond its `viewBox`.
- **`role="img"` + `aria-label`** (from `title`) for accessibility.

## Prop & Emit Contract

- `title`: accessible label (default `Guilherme Battoni`).

## Do Not

- Do not hard-code a fill colour — use `currentColor`.
- Do not set a fixed width/height — size from the parent.
