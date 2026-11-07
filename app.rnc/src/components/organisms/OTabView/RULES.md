---
version: 1.2.0
origin: pendulum
based-on: 1.2.0
---

# RULES — OTabView / OTabPanel

> For AI agents. Last updated: 2026-09-15.

## Purpose

A reusable tabs organism with two modes:

- **`view`** (default) — classic tabs. Each tab's content is a slot; switching is handled in-memory via `v-model`.
- **`routes`** — each tab maps to a route. The body is a single `<RouterView/>`; switching navigates with the router and the active tab is derived from the current route.

`OTabView` is the parent; `OTabPanel` is a **renderless descriptor** for one tab (title, value, icon, route, actions slot, content slot). Multiple `OTabView`s may exist on the same page; it commonly replaces or sits alongside `TheFilters`.

## The descriptor pattern (CRITICAL — read before editing)

`OTabPanel` is **never mounted**. `OTabView` reads its own default-slot VNodes (`slots.default()`), filters to `OTabPanel` nodes by component name, and pulls everything off each VNode:

- `vnode.props` → `title`, `value`, `icon`, `to` — read through `readPanelProp`, which accepts the kebab-case attribute too: Vue normalises a kebab-case attribute only for a component it instantiates, and a panel never is
- `vnode.children` → the slot functions: `default` (renamed `content`) and `actions`

`OTabView` then renders the strip (`Tabs` / `TabList` / `Tab`) and the body itself, running the captured slot functions via `<component :is="...">`. This is why the actions slot (declared inside a panel) can render up in the **header** while the content renders in the **body**.

Consequences to respect:

- **`OTabPanel`'s own `<template>` (`<slot/>`) is a graceful fallback only** — in normal use it does not run, so do not add logic/lifecycle to `OTabPanel`. It exists to declare props/slots.
- **`withDefaults` defaults on `OTabPanel` would NOT apply** (the instance is never created). Read defaults in `OTabView` instead — see `resolveTabKey`.
- **Panel identity is matched by component name** (`__name`/`name === 'OTabPanel'`) in `isTabPanel`. Keep `defineOptions({ name: 'OTabPanel' })`.
- **`v-for` over panels is flattened one level** (`flattenPanels` expands `Fragment` children). Deeper nesting is not supported.

## Intentional Decisions

- **`activeTabKey` is a writable `computed`, not a plain ref** — its `get` derives the active key (from the `v-model` model in `view` mode, from the route in `routes` mode); its `set` updates the model (`view`) or `router.push`es the tab's `to` (`routes`). This is what lets `<Tabs v-model:value>` work over a route-derived value.
- **Typed `computed<string | number>`** — matches PrimeVue `Tabs`' `value` on both directions. `set` normalises to `String(value)` before touching the `string` model.
- **`title` is an i18n key** — rendered with `$t(tab.title)`, mirroring `MMainDialog`. Pass keys, not pre-translated strings.
- **Routes-mode active match is by route `name`** (`router.resolve(tab.to).name === route.name`). Each tab passes its own `to`, so this is exact by design.
- **The strip scrolls; it never widens the page.** `Tabs` is `scrollable` and the tree down to `TabList` is `min-w-0 max-w-full`: a flex child's minimum width is its content, so a strip of long titles on a phone pushed the whole screen sideways (from new.impressao).
- **No keep-alive** — switching unmounts the previous tab's content (consistent with routes mode). Add keep-alive deliberately if a tab must preserve state.

## Prop & Slot Contract

### OTabView

- `mode?: 'view' | 'routes'` — default `'view'`.
- `v-model` (`modelValue: string`) — active tab key in `view` mode. Ignored in `routes` mode (route drives it).
- Default slot — a list of `OTabPanel`s.

### OTabPanel

- `title: string` (required) — i18n key shown in the tab header.
- `value?: string` — tab key. Falls back to the resolved route name, then the index.
- `icon?: string` — `pi` icon class shown before the title.
- `to?: RouteLocationRaw` — required in `routes` mode; the tab's navigation target.
- `#actions` slot — rendered in the tab header (wrapped in `@click.stop` so it never switches the tab).
- default slot — the tab body. Used in `view` mode only (ignored in `routes` mode, where `<RouterView/>` renders).

## Do Not

- **Do not render `<slot/>` for panel bodies in `OTabView`** — it reads VNodes; rendering the slot would mount panels and double-render.
- **Do not move `OTabPanel` to its own folder or rename it** without updating the `isTabPanel` name check.
- **Do not pass component references through a config array** — content belongs in `OTabPanel`'s default slot (auto-import friendly, no `v-if` chains).
- **Do not pre-translate `title`** — pass the i18n key.
