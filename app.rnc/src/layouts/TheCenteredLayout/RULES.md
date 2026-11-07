---
version: 1.1.1
origin: pendulum
based-on: 1.1.1
---

# RULES — TheCenteredLayout

> For AI agents. Last updated: 2026-05-22.

## Purpose

Full-page centered layout for unauthenticated/auth pages (login, sign-up, forgot password). Centers a fixed-width content column both horizontally and vertically.

## Intentional Decisions

- **No script block**: pure template. There is no state, no composables, no props. Do not add them unless the layout genuinely needs it.
- **`max-w-[360px]`**: the fixed auth column width is intentional. Do not widen — auth forms are designed for this constraint.
- **`min-h-screen`**: ensures the grid fills the viewport even when content is shorter than the screen.
- **`place-items-center` on a grid**: used instead of flexbox centering because `grid` + `place-items-center` vertically centers a single child without requiring `h-full` on the child.

## Do Not

- **Do not add `TheNavbar`** or any authenticated UI to this layout.
- **Do not change `max-w-[360px]`** — the auth form components are designed around this width.
