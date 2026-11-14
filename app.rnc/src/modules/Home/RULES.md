---
version: 3.7.0
origin: pendulum
based-on: 2.0.0
---

# RULES — Home Module

> For AI agents. Last updated: 2026-11-07 (rnc-1 F1).

## Domain

The back office's landing (`/painel`, route name `home`) and the three workspace landings the rail
opens: Equipe (`team-home`), Reinaldo (`management-home`) and Operador (`operator-home`).

## Public Barrel

`views` (route configs `HomeRoutes`, `WorkspaceRoutes`), `composables` (`useWorkspaces`,
`visibleWorkspaces`), `constants` (`WORKSPACES`), types.

## Intentional Decisions

- **`/gestao` falls back to the metrics without `briefing`** (rnc-4 F3c): the redirect routes carry `meta.moduleFallback`, which `attachAuthGuard` reads from `redirectedFrom` when the briefing's module is off — by path, Home still imports nothing from Metrics or Briefing.
- **`WORKSPACES` is the role table read through permissions** (plan AM8): `leads.read` opens Equipe,
  `metrics.read` Reinaldo, `users.read` Operador. The rail (`TheNavbar/navItems.local.ts`), the cards
  on `HomeView` and the routes' `meta.permission` all read the same keys. Never gate on a role slug.
- **`home` left `/` to the public site** (AM15) and keeps its literal name — the guards and the login
  screens push `{ name: 'home' }`. It carries no `meta.permission` on purpose: a refused navigation
  is redirected to `home`, so a key on it would loop.
- **One `WorkspaceView` for the landings**, told apart by `meta.workspace`. Each was an empty state
  in F1; later phases add their own screens as routes and rail children. The team's landing
  (`team-home`) now redirects to its pipeline (F5) and Reinaldo's (`management-home`) to his daily
  briefing (rnc-3 F9, R26), by path — Home imports nothing from Pipeline or Briefing. _Retired
  07/11/2026 (rnc-3 F9):_ "Reinaldo's landing redirects to his metrics (F7)" — R26 made "Briefing do
  dia" the screen he opens on; the metrics stay a rail child. The group itself stays on
  `metrics.read`: every role holding it (gestao, operador) holds `briefing.read` (RoleSeeder).

- **A partner never sees Home** (rnc-2 R3, A4): `HomeView` replaces itself with the portal when
  `usePortalLanding().isPortalOnly` (`portal.access` and no `home.read`) and renders nothing. The
  guard falls every refused route back to `home`, so this one redirect bounces a partner from the
  whole back office; the operator holds both keys and stays. The portal is reached by route name
  through `@PortalModule`'s barrel.
- **The firm-less platform operator never sees Home either** (rnc-4 F4b, R24): with
  `platform.is_operator`, no `firm` and the key `platform.firms`, `HomeView` replaces itself with the
  platform console (`@PlatformModule`'s `PLATFORM_ROUTE_NAMES.firms`). The key is part of the check so
  a console the person cannot open never bounces back here in a loop.

_Retired 07/11/2026:_ "Placeholder home view — do not add features until the product home screen is
designed". The product (rnc-1) designed it: a greeting and the person's workspaces.

## Do Not

- **Do not add a prototype screen here** to demonstrate a component — build it where it is used.
