---
version: 1.1.2
origin: pendulum
based-on: 1.1.2
---

# RULES — src/modules/

> For AI agents. Last updated: 2026-09-16.

## Purpose

Feature modules organized by bounded context. Each module owns its own components, views, services, store, types, and public barrel.

## When to create a new module

Create a new module when the feature represents a **distinct domain** with its own entities, routes, and services. Do not create a module for a single view or a minor feature extension of an existing domain.

Examples: `Auth` (authentication, roles, permissions), `User` (user management), `Home` (dashboard/marketing pages).

## Module Structure Contract

Every module must follow this structure:

```
ModuleName/
  components/          ← module-specific components (atoms/molecules/organisms sub-folders)
  services/            ← API service functions
  views/               ← route-level views
  store.ts             ← Pinia store (if module has persistent state)
  enums.ts             ← module enums (UPPER_SNAKE_CASE)
  interfaces.ts        ← public interfaces and payload types
  types.ts             ← type aliases
  routes.ts            ← Vue Router route definitions
  index.ts             ← public barrel (re-exports everything public)
```

Optional: `layouts/`, `composables/`, `helpers/`, `data/`, `constants.ts`

## Public Barrel (`index.ts`) Contract

Everything a consumer needs from this module must be exported from `index.ts`. Consumers import exclusively from the module root alias (e.g. `@AuthModule`, `@UserModule`).

**Never import from a sub-path** (`@AuthModule/services`, `@AuthModule/views`, etc.) — if something is missing from the barrel, add it there.

## Module Store Location

Module stores live at `ModuleName/store.ts` (not in `src/stores/`). Export the store via the module barrel.

## Cross-Module Imports

Modules may import from other modules via their root alias. They must **not** reach into another module's internal files by path. Circular imports between modules are not allowed.

## Current Modules

| Module            | Alias                    | Domain                                                               |
| ----------------- | ------------------------ | -------------------------------------------------------------------- |
| `Auth`            | `@AuthModule`            | Authentication, roles, permissions                                   |
| `Home`            | `@HomeModule`            | Back-office landing and the three workspaces (Equipe · Reinaldo · Operador) |
| `Personalization` | `@PersonalizationModule` | Global UI preferences (dark mode, language, layout) — this browser only |
| `Site`            | `@SiteModule`            | Franco's public site (open routes, `ThePublicLayout`)                |
| `User`            | `@UserModule`            | User management, session state                                       |

*Retired 07/11/2026 (rnc-1 F1):* `Idea`, `Showcase` and `Tip` — bootstrap samples this product does
not use, removed with their tests, routes, aliases and locale keys.

## Do Not

- **Do not import from module sub-paths** (`@AuthModule/services`) — always import from the root alias.
- **Do not add shared components to a module** if they're used by 2+ modules — promote to `src/components/`.
- **Do not put app-wide state in a module store** — use `src/stores/` for cross-module state.
