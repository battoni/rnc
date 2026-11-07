---
version: 1.3.1
origin: pendulum
based-on: 1.3.1
---

# RULES — App Root (App.vue)

> For AI agents. Last updated: 2026-09-22.

**Short on purpose.** This file is injected into EVERY Vue/TS edit under `src/`, so each line here
is paid for on every edit in the app. A rule about one unit goes in that unit's `RULES.md`; a
convention for the whole app goes in `.claude/rules/celer-*`. What stays here is `App.vue` and
nothing else.

## Purpose

Application root component. Mounts the global `Toast` provider, then `TheRouteGuard` wrapping the
`RouterView` outlet (inside the route `Transition`), and reads `?locale=` once.

## Intentional Decisions

- **`Toast` is placed before `RouterView`**: PrimeVue Toast renders in a portal; placing it first ensures it is always in the DOM before any view tries to add a toast.
- **`colorSchemeStore.init()` and `uiStore.init()` on mount**: restores persisted theme and UI preferences (fullscreen, tutor) before first paint interaction.
- **No layout here**: all layout (navbar, content wrapper) lives in `TheLayout` and `TheCenteredLayout`, used by individual views. `App.vue` is intentionally bare.
- **Route fade uses `mode="out-in"`.** Two full-height layouts in flow at once shove each other down the page. The opacity transition targets `[data-route-fade]` (CentredLayout root vs TheLayout child column) so the rail holds steady. NotFound → Home blank is fixed with a **named** `push` in NotFoundView — not by dropping out-in.
- **No absolute leave / no simultaneous fade.** A brief experiment (no out-in + `position: absolute; inset: 0` on leave + `:key="route.fullPath"`) invented thin vertical and horizontal scrollbars on every page in DevTools mobile. Retired: out-in stays.

## Do Not

- **Do not add layout, more guards, or composables** to `App.vue` — use router guards or the layout components instead. Two exceptions are deliberate and are the only ones: `TheRouteGuard` wraps the `RouterView` (the hard-navigation gate has to sit around the outlet, before any view mounts), and `useLocaleFromQuery()` is called here (the app has exactly one router and one global locale, so `?locale=` is read once, at the root).
  _Retired:_ "Do not add layout, auth guards, or composables to `App.vue`" — it was written before both exceptions existed and `App.vue` contradicted it.
- **Do not add a second `<Toast>`** anywhere in the component tree — one global instance is sufficient.
- **Do not drop `mode="out-in"`** for a simultaneous fade + absolute leave — that is how global page scrollbars returned.
- **Do not "fix" NotFound → Home blank by changing this Transition** — use named navigation from NotFoundView.
- **Do not let a routed view have two root nodes** (an element plus a sibling comment counts). `out-in` cannot drive a fragment-rooted child: the leave never ends, the next view never mounts, and the app is blank until a reload — with nothing in the console. Guarded by `src/test/routedViewSingleRoot.spec.ts`.
