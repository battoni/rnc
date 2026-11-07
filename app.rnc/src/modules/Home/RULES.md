---
version: 3.0.0
origin: pendulum
based-on: 2.0.0
---

# RULES — Home Module

> For AI agents. Last updated: 2026-11-07 (rnc-1 F1).

## Domain

The back office's landing (`/painel`, route name `home`) and the three workspace landings the rail
opens: Equipe (`team-home`), Reinaldo (`ceo-home`) and Operador (`operator-home`).

## Public Barrel

`views` (route configs `HomeRoutes`, `WorkspaceRoutes`), `composables` (`useWorkspaces`,
`visibleWorkspaces`), `constants` (`WORKSPACES`), types.

## Intentional Decisions

- **`WORKSPACES` is the role table read through permissions** (plan AM8): `leads.read` opens Equipe,
  `metrics.read` Reinaldo, `users.read` Operador. The rail (`TheNavbar/navItems.local.ts`), the cards
  on `HomeView` and the routes' `meta.permission` all read the same keys. Never gate on a role slug.
- **`home` left `/` to the public site** (AM15) and keeps its literal name — the guards and the login
  screens push `{ name: 'home' }`. It carries no `meta.permission` on purpose: a refused navigation
  is redirected to `home`, so a key on it would loop.
- **One `WorkspaceView` for the three landings**, told apart by `meta.workspace`. Each is an empty
  state in F1; later phases add their own screens as routes and rail children.

*Retired 07/11/2026:* "Placeholder home view — do not add features until the product home screen is
designed". The product (rnc-1) designed it: a greeting and the person's workspaces.

## Do Not

- **Do not add a prototype screen here** to demonstrate a component — build it where it is used.
