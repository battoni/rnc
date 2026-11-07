---
version: 1.0.0
origin: pendulum
based-on: 1.0.0
---

# RULES — ABrandPattern

> For AI agents.

## Purpose

A decorative, low-opacity tiling of the brand monogram — a technical watermark backdrop. Uses `currentColor`; the caller positions it (e.g. absolute inset-0) and sets colour + opacity.

## Intentional Decisions

- **`useId()` per instance**: the `<pattern>` id is unique so multiple instances on one page never collide.
- **`aria-hidden`**: purely decorative.
- **`currentColor`**: colour is driven by the caller's text utility; opacity by the caller's wrapper.

## Prop & Emit Contract

- `tile`: tile size in px (default `150`).
- `rotate`: pattern rotation in degrees (default `0`).

## Do Not

- Do not give it a fixed colour or opacity — those belong on the caller's wrapper.
