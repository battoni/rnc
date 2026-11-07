---
version: 1.3.1
origin: pendulum
based-on: 1.3.1
---

# RULES — ONavbarDrawer

> For AI agents. Last updated: 2026-09-17.

## Purpose

Slide-in drawer navigation for tablet (md) and mobile viewports. Hidden on lg+.

## Intentional Decisions

- **`lg:hidden` on the Drawer root**: this component is never visible on desktop.
- **`drawerClass` and `drawerPosition` are passed from `TheNavbar`**: mobile uses `position='full'` + class `'nav-drawer-mobile'`; tablet uses `position='left'` + class `'w-54'`. Do not hardcode positions or classes here.
- **`onCloseAction` wraps PrimeVue's `closeCallback`**: it calls `closeCallback()` first, then emits `close`. Order matters — closing the Drawer must happen before the parent reacts.
- **`onLogoutClick` calls `closeCallback` and emits `close` before `logout`**: the drawer must close before the logout redirect navigates away, otherwise the drawer remains mounted during navigation.
- **RouterLink click handlers call `onCloseAction(closeCallback)`**: the drawer closes when the user navigates to any item.
- **`update:visible` is forwarded to parent unchanged**: the parent (`TheNavbar`) controls `uiStore.isSidenavOpen` via this event.
- **Groups render exactly as in `ONavbarDesktop`** — the `0fr → 1fr` grid track, the `inert` collapsed block, the two row shapes decided by whether the group declares a route, the `bg-primary-100` icon on the exact-active child against the row-tint-only hint on its parent, and the `pl-[18px] text-sm text-muted min-h-10 pointer-coarse:min-h-11` child (44px under a finger). The rail and the drawer are the same navigation at two widths; a change to one is a change to both.
- **The child indent is 18px on purpose, and it is not on Tailwind's scale.** The owner chose 18px; the scale offers 16px (`pl-4`) and 20px (`pl-5`) and neither is the value asked for, so the arbitrary `pl-[18px]` is deliberate. It sits after `p-1` in the class list because `p-1` sets all four sides and the left override must win. Do not "correct" it to a scale step, do not reorder the two, and do not change it here without changing `ONavbarDesktop` in the same breath.
- **The container is painted `bg-canvas`.** The drawer is a ground of its own, and PrimeVue's
  `--p-drawer-background` (`#252626` in dark) was never one of the grounds the text tiers are
  measured on: `muted` read 4.43:1 and the chevron (`subtle`) 2.99:1 there. On the canvas both clear
  their floors, the rows' `bg-panel` hover and active tints lift off it exactly as they do in the
  rail, and light is unchanged (the drawer was already white). `e2e/theme/contrast.spec.ts` measures
  it as it renders.
- **The close button is named and thumb-sized** — `:aria-label="$t('common.actions.close')"` and `pointer-coarse:size-11`. It is icon-only, and a default-size button gets no touch growth.
- **Toggling a group must NOT close the drawer.** Only navigation closes it — `onGroupToggle` deliberately does not call `closeCallback`. Opening a group to reach its child inside a drawer that dismissed itself on the first tap is the bug this rule exists to prevent.
- **A label is an i18n key unless the entry says otherwise.** `toLabel(item)` renders `item.label` as-is when `isLabelLiteral` is set — an entry built from a person's own record (a trail's name) names itself, and `$t()` would translate a name that happened to collide with a key.
- **A child link closes the drawer like any other link**: the child `RouterLink` carries the same `@click="onCloseAction(closeCallback)"` as a top-level one.
- **An opened group is scrolled into view, exactly as in `ONavbarDesktop`.** `revealGroup` finds it by `[data-nav-group="<key>"]` under the `navScrollArea` ref and hands it to `revealNavGroup` (`../../../navGroupScroll`); the reveal runs on the track's `@transitionend.self` (settled layout) plus a `nextTick` pass when no other group was open. It matters more here than in the rail: the drawer's list is the whole screen, so a group that unfolds below the fold hides its children behind a scroll nobody was told about.

## Prop & Emit Contract

- `personalizationWithRoute`: link to the Personalization page, after settings and before logout.
- `drawerClass`: CSS class(es) for the Drawer root. Comes from `TheNavbar`'s computed `drawerClass`.
- `drawerPosition`: PrimeVue Drawer position. Comes from `TheNavbar`'s computed `drawerPosition`.
- `visible`: controlled externally by `uiStore.isSidenavOpen`.
- `itemsWithRoutes`: `NavRouteItem[]`, already gated and resolved — `route` absent on a pure expander, `children` absent on a plain link.
- `expandedGroupKeys`: the group keys currently expanded, from `useUiStore`. Shared with `ONavbarDesktop`, so a group opened in one is open in the other.
- `docsLink` (`NavExternalItem | null`, optional): the documentation portal, built by `TheNavbar` from `VITE_DOCS_URL`. Rendered after Personalization and before logout as a plain `<a data-testid="nav-docs">` opening in a new tab (`rel="noopener noreferrer"`). `null` or absent renders nothing — never a link that goes nowhere. Covered by `navDocsLink.spec.ts` in `TheNavbar`.
- `@close`: emitted after `closeCallback` is called — parent should sync `uiStore.isSidenavOpen` if needed.
- `@update:visible`: forwarded from PrimeVue Drawer's visibility events.
- `@logout`: bubbled to `TheNavbar`.
- `@toggle-group(groupKey)`: asks `TheNavbar` to flip one group. Never closes the drawer.

## Do Not

- **Do not colour the dividers at the call site** — the preset paints `Divider` with `--color-line`.
- **Do not paint the container with the preset's drawer background** — see `bg-canvas` above.

- **Do not call `emit('logout')` before closing the drawer** — the drawer must be dismissed first to avoid UI glitches during route navigation.
- **Do not put a colour-scheme control here** — the scheme is a visual preference among others and is configured on the Personalization page, not given a permanent slot in the navigation.
- **Do not add permission filtering** — items arrive pre-filtered from `TheNavbar`.
- **Do not close the drawer on a group toggle** — see above; expanding is not navigating.
