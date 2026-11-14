---
version: 1.4.2
origin: pendulum
based-on: 1.4.1
---

# RULES — ONavbarDesktop

> For AI agents. Last updated: 2026-09-17.

## Purpose

The sticky left sidebar navigation for desktop viewports (lg+). Hidden below lg.

## Intentional Decisions

- **`hidden ... lg:block` on the outer wrapper**: this component renders nothing below `lg`. All logic below `lg` is handled by `ONavbarDrawer` and `ONavbarMobile`.
- **`lg:sticky lg:top-8`**: the sidebar sticks as the user scrolls. The `h-[calc(100dvh-4rem)]` on the Card ensures it fills the viewport.
- **Custom `RouterLink` with `v-slot`**: uses the render prop pattern to access `isExactActive` and `href` for custom link styling. This is required because PrimeVue's `Menu` component doesn't support custom active-state classes.
- **`isExactActive` not `isActive`**: parent route segments do not get highlighted — only the exact active route.
- **The top of the rail is `MNavbarBrand`** (rnc-4 R10): the firm's tile, logo text and `platform.name`.
  _Retired 14/11/2026:_ "Logo renders `ALogo` with `variant=\"min\"`" — a bare tile could not say which firm or which product.
- **Settings section is in the Card `#footer` slot** (sticky bottom), not inside the scrollable content area.
- **Only the middle block scrolls.** `pt:body` and `pt:content` carry `min-h-0` so the flex chain's min-content floor is broken and the item list — not the rail, not the page — takes the overflow. Logo stays pinned above it, settings below it in the footer, and expanding a group only ever grows this one scroll area. Removing either `min-h-0` makes the whole rail grow past the viewport instead.
- **The scroll surface is `scrollbar-brand`**, the house treatment (terracotta thumb, transparent track, `scrollbar-width: thin`) shared with the week grid and `MMainDialog`. Never restyle a scrollbar locally.
- **A group's children collapse as a `0fr → 1fr` grid track**, the same trick the rail itself collapses with: height interpolates out of `auto` with nothing hard-coded, so nothing jumps. A `max-height` guess is not an acceptable substitute.
- **A collapsed group is `inert`, not unmounted.** The track clips it visually; `inert` is what also takes its links out of tab order. `toGroupInert` returns `true | undefined` and never `false` — `:inert="false"` ships as `inert="false"`, which HTML reads as inert.
- **Two different rows, decided by the data.** A group with a route renders a `RouterLink` for the text plus a separate chevron `<button>` that only toggles; a group without one renders a single `<button>` that toggles. Both carry `aria-expanded`; the chevron-only button also carries an `aria-label`, because it has no text of its own.
- **Active-state hierarchy is deliberate.** An exact-active link gets the row tint _and_ the `bg-primary-100` icon chip — "you are here". A parent whose child is active gets the row tint only (`isGroupActive`) — "what you are looking at lives in here". Do not give the parent the peach as well; the two states must stay distinguishable.
- **A label is an i18n key unless the entry says otherwise.** `toLabel(item)` renders `item.label` as-is when `isLabelLiteral` is set — an entry built from a person's own record (a trail's name) names itself, and `$t()` would translate a name that happened to collide with a key.
- **Children are quieter and indented**: `pl-[18px]`, `text-sm text-muted`, a smaller icon, and `min-h-10 pointer-coarse:min-h-11`: 40px under a mouse, 44px under a finger (celer-06). _Retired wording:_ "`min-h-10` so the hit target stays at 40px" — 40px is under the touch floor, and a tablet wide enough for the rail is still touched.
- **The child indent is 18px on purpose, and it is not on Tailwind's scale.** The owner chose 18px; the scale offers 16px (`pl-4`) and 20px (`pl-5`) and neither is the value asked for, so the arbitrary `pl-[18px]` is deliberate. It sits after `p-1` in the class list because `p-1` sets all four sides and the left override must win. Do not "correct" it to a scale step, and do not reorder the two.
- **An opened group is scrolled into view, inside this scroll area only.** `revealGroup` finds the group by `[data-nav-group="<key>"]` under the `navScrollArea` ref and hands it to `revealNavGroup` (`../../../navGroupScroll`), which moves the container just far enough to show the last child while never pushing the header above the top edge. `scrollIntoView` is the wrong tool: `block: 'nearest'` walks every scrollable ancestor, and the page must not move when a nav group opens.
- **The reveal runs on `@transitionend.self` of the track, and on `nextTick` only when nothing else was open.** The children's height comes from a `0fr → 1fr` track, so at the moment of the click the group still measures collapsed and a sibling that is closing still measures open — measuring then would overshoot by exactly that sibling's height. The transition's end is the first settled layout. The `nextTick` pass exists so the reveal still happens where the transition never runs; `onGroupToggle` skips it when another group is collapsing. `.self` keeps a child link's colour transition from re-triggering it.
- **`data-nav-group` is a hook, not a style.** It carries the group's key so the reveal can find one group without a ref registry. Do not style off it, and do not remove it.

## Prop & Emit Contract

- `personalizationWithRoute`: link to the Personalization page (`pi-cog`), rendered after settings and before logout.
- `itemsWithRoutes`: primary nav items, already gated and resolved (`NavRouteItem[]`). Each item: `{ icon, key, label, route?, routeName?, children? }` — `route` is absent on a pure expander, `children` on a plain link.
- `settingsWithRoutes`: secondary nav items (bottom section), `NavLinkItem[]` — the settings block renders flat links only, so every entry carries a route.
- `expandedGroupKeys`: the group keys currently expanded, from `useUiStore`. This component owns no expansion state of its own.
- `docsLink` (`NavExternalItem | null`, optional): the documentation portal, built by `TheNavbar` from `VITE_DOCS_URL`. Rendered after Personalization and before logout as a plain `<a data-testid="nav-docs">` opening in a new tab (`rel="noopener noreferrer"`). `null` or absent renders nothing — never a link that goes nowhere. Covered by `navDocsLink.spec.ts` in `TheNavbar`.
- `@logout`: bubbles up to `TheNavbar` which handles the actual logout logic.
- `@toggle-group(groupKey)`: asks `TheNavbar` to flip one group. Fire and forget — the answer comes back as a new `expandedGroupKeys`.

## Do Not

- **Do not colour the dividers at the call site** — the preset paints `Divider` with `--color-line`. The `before:border-line` the rail used to carry undid a brand-coloured default that no longer exists.

- **Do not add permission logic here** — items are pre-filtered by `TheNavbar` before being passed as props.
- **Do not put a colour-scheme control here** — the scheme is a visual preference among others and is configured on the Personalization page, not given a permanent slot in the navigation.
- **Do not change `isExactActive` to `isActive`** — parent routes should not appear highlighted. A group header's hint comes from `isGroupActive`, which asks the declared children, not the URL prefix.
- **Do not `v-if` a collapsed group away** — the animation needs it mounted, and `inert` is what makes it unreachable.
- **Do not reach for `scrollIntoView`** to reveal a group — it scrolls every scrollable ancestor, the page included. The rail is the only thing that may move.
- **Do not let the whole row toggle when the group has a route** — the text must navigate, or the group's own page becomes unreachable from the rail.
