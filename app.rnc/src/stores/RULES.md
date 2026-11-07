---
version: 1.5.0
origin: pendulum
based-on: 1.5.0
---

# RULES — src/stores/

> For AI agents. Last updated: 2026-09-17.

## Purpose

Global Pinia stores for app-wide shared state that must persist across components and is not owned by a single module.

## Global Store vs. Module Store — Decision Guide

**Put it here (`src/stores/`)** when:

- State is consumed by 2+ unrelated modules (e.g. UI state needed by layout AND feature views)
- State has no clear module owner

**Put it in `src/modules/ModuleName/store.ts`** when:

- State is specific to one module's domain (e.g. `useUserStore` lives in `User/store.ts`)
- The store is the primary source of truth for that module's entities

## Current Stores

| File                   | Store ID        | Purpose                                                                        |
| ---------------------- | --------------- | ------------------------------------------------------------------------------ |
| `ui.store.ts`          | `'ui'`          | App-wide UI state: sidenav, rail, fullscreen layout, tutor mode, viewport size |
| `colorScheme.store.ts` | `'colorScheme'` | Light/dark preference with `data-theme` on `<html>`                            |
| `tip.store.ts`         | `'tip'`         | Active contextual tips (anchor → content) and the tip being edited             |

## Conventions

- File name: `camelCase.store.ts`
- Default export with `defineStore`
- Store ID matches the file name (e.g. `'ui'` for `ui.store.ts`)
- Imported via the `@Stores` alias: `ui` is the default export (`import useUiStore from '@Stores'`), the others are named (`useColorSchemeStore`, `useTipStore`)
- Use options API style (`state`, `actions`, `getters`) for consistency with existing stores

## `ui.store.ts` specifics

- **`useWindowSize()` is called at module level** (outside `defineStore`): this is intentional — `@vueuse/core`'s `useWindowSize` must be called once and shared, not per-store-instance.
- **`appHeight` / `appWidth` getters**: use `() => height.value` (not `(state) => ...`) because they read from the module-level refs.
- **`isFullscreen` / `isTutorEnabled`**: persisted as `'1'`/`'0'` in localStorage (`app.rnc:fullscreen`, `app.rnc:tutor-enabled`). Tutor defaults to **enabled**.
- **Rail state is persisted** as `'1'`/`'0'` (`app.rnc:nav-collapsed`, `app.rnc:menu-button-pinned`) and a comma-joined list (`app.rnc:nav-expanded-groups`). All three are opt-in: a fresh install shows the rail with the menu button out of the way.
- **Collapsed without pinned is not a state.** The menu button is the only way back to a collapsed rail, and it only shows at every width when pinned. `toggleMenuButtonPinned()` reopens the rail when it unpins, and `readStoredNavCollapsed()` re-checks the pair on read — a stored combination from an older build or another tab must not boot the app with no navigation.
- **One expanded nav group at a time.** `toggleNavGroup()` opens one and closes the rest; a stored list naming several keeps the first.
- **`toggleNavCollapsed()` also closes the drawer**, so the rail and the drawer never claim the screen at once.
- **`init()`**: calls `applyTutor()` only — persisted flags are read when the store state is created. Must run from `App.vue` on boot alongside `colorSchemeStore.init()`.
- **`setTutorEnabled(false)`**: calls `resetSingleTipState()` from `@Composables` to close any open tip.

## `colorScheme.store.ts` specifics

- Persists to `app.rnc:color-scheme` (kept in sync with anti-FOUC script in `index.html`).
- `setPreference` accepts `LIGHT | DARK | SYSTEM`; Personalization page toggles only `LIGHT`/`DARK`.

## `tip.store.ts` specifics

- **`ensureLoaded()` is lazy and shared**: one request per session, started by the first `<ATip>` that mounts, and a module-level `inFlight` promise answers every other caller while it runs. It is not wired into boot — it needs a session.
- **Its `.catch` swallows on purpose**: every caller is an atom that can do nothing about the failure; `isLoaded` stays false and the next tip retries.
- **`applyTip(tip)`** mirrors a management-view change into what the app renders (moving or archiving empties the old anchor). Logout wipes its state through `resetAllStores` (`$reset`), so the next session loads its own tips; `reset()` also drops a pending `inFlight`.
- `editingAnchorKey` drives the single `OTipEditDialog` mounted by `TheLayout` (`requestEdit` / `closeEdit`).

## Do Not

- **Do not store domain entities** (users, roles, etc.) in global stores — those belong in module stores.
- **Do not call `useWindowSize()` inside `defineStore`** — it must be at module level (see above).
- **Do not create a global store for state used by only one module** — put it in the module's `store.ts`.
