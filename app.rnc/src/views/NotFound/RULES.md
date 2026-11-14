---
version: 1.0.4
origin: pendulum
based-on: 1.0.3
---

# RULES — NotFoundView

> For AI agents. Last updated: 2026-09-15.

## Purpose

What an address nobody can serve renders: the product mark, a sentence that says the page does not exist, and the way back to Home. Mounted by the catch-all in `routes.ts`.

## Intentional Decisions

- **Declared last** in `libraries/router/routes.ts`, for the reader. vue-router 4 ranks routes by how specific their path is, not by order, and `/:pathMatch(.*)*` ranks below everything — so the catch-all loses to any real route, a project's `routes.local.ts` included, wherever it sits.
- **`meta.isOpen`, not `isPublic`.** The guard sends a signed-in person away from a public route (to Home) and a signed-out one away from a private route (to the login screen). A wrong address is wrong for both, so `attachAuthGuard` lets an open route through before either check. Without it a signed-in typo lands on Home and nobody is told.
- **`TheCenteredLayout`**, the same shell as the auth screens: it has to render without a session.
- **Home via `push({ name })`, not a path string.** `resolve(...).path` produced `'/'`; leaving the catch-all that way under App.vue's `mode="out-in"` route-fade left Home blank or invisible until reload. Named navigation is the fix — do not drop out-in on App.vue for this (that invented global page scrollbars).

## Do Not

- **Do not give a real route a path pattern as loose as the catch-all's** — it would tie on rank with it.
- **Do not mark it `isPublic`** — see above.
- **Do not navigate Home with `push('/')` or `resolve(...).path`** — use the route name.
