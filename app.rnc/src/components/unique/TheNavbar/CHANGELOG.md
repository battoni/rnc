# Changelog — TheNavbar

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.11.0 — 2026-11-14

- The firm's own settings (rnc-4 F5b, R30–R33): `navItems.local.ts` ends "Gestão" with contact, team logins, specialists and consent, all four behind `firm.settings` (the manager's key; the operator holds every key). `TheNavbar.vue` is untouched.

## 1.10.0 — 2026-11-14

- "Plataforma" (rnc-4 F4b, R24, R25): `navItems.local.ts` closes the rail with the platform console — firms and default consent — behind `platform.firms` / `platform.consent`, keys only the firm-less operator holds. `key: 'platform'` because the group and its first child resolve the same route. `TheNavbar.vue` is untouched.

## 1.9.0 — 2026-11-14

- Rail entries per firm module (rnc-4 F3c, R17, A24): `navItems.local.ts` entries carry `module` (`LocalNavItem`) and `useLocalNavItems` drops those the firm has off, children included — partners, trail, ledger and the portal without `partners`, and so on. `TheNavbar.vue` is untouched.

## 1.8.0 — 2026-11-14

### Changed

- The manager's rail group key `ceo` → `management`, its label key `navigation.management`, and the mobile route `management-home` (rnc-4 F2c1, R14).

## 1.7.0 — 2026-11-14

### Added

- `MNavbarBrand` (rnc-4 F2b, R10): the firm's tile, logo text and `platform.name` at the top of the rail
  and the drawer, replacing the bare `ALogo` tile. `MNavbarBrand.spec.ts` (new); the organisms' specs
  stub it in place of `ALogo`.

## 1.6.3 — 2026-09-17

### Fixed

- `RULES.md` said the Design group has three children (Brand, Components, Showcase); it has four — Features too, gated on `design.read` like the rest (lifeshifter-377). Doc only.

## 1.6.2 — 2026-09-17

### Changed

- The specs (`TheNavbar`, `ONavbarDesktop`, `ONavbarDrawer`) use neutral fixtures — `inbox`,
  `reports`, `trends`, `workspace` — instead of a downstream product's `today` / `season` /
  `retro` / `trails`. Same cases, same assertions. The logout comment no longer names that
  product's stores.

## 1.6.1 — 2026-09-17

### Changed

- `TheNavbar.spec.ts`: the nested-route fixture is `projects` / `project-7` / `/projects/:id` instead of a downstream product's `tracks` vocabulary. Same cases, same assertions.

## 1.6.0 — 2026-09-16

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

### Added

- A documentation entry above Log out, in the rail and the drawer, when `VITE_DOCS_URL` names a portal. It is a `NavExternalItem`, not a `NavItem` with an `href`: everything in that pipeline resolves a route name, gates on a permission and renders a `RouterLink`, so an entry doing none of those would be three `v-if`s deep in every variant. Unset, the entry does not exist — never a link that goes nowhere (from new.impressao).

## 1.5.1 — 2026-09-15

### Fixed

- Rail clip wrapper uses `lg:-mx-3 lg:px-3` instead of always-on negative margins. Below `lg` the
  track is `w-0`; the margins still expanded past the viewport and caused horizontal (+ vertical)
  scroll on every authenticated phone page.

## 1.5.0 — 2026-09-14

### Changed

- The Design group and its four children are gated on `design.read` instead of `role: ROLES.SUPERADMIN`. The RULES already said so and the code did not: an earlier port brought the text and left the change behind. The routes carry the same key in `meta.permission` (from lifeshifter).

### Fixed

- The spec pinned the role gate on purpose ("a ROLE, not a permission"); it now pins the permission — an administrator without the key sees no Design, and a plain account holding it does.

## 1.4.0 — 2026-09-09

### Changed

- Logout treats HTTP 401 as already signed out: clear session stores and go to login instead of an error toast that left the UI looking signed in.

## 1.3.0 — 2026-09-06

### Added

- Navigation nests one level. A `NavItem` may declare `children`, and **the shape it declares is
  the behavior it gets**: `routeName` alone is a plain link, `children` alone is an expander, both
  is a link whose text navigates while a chevron toggles. Nothing carries a `type` flag, so the
  data and the behavior cannot drift apart. `toVisibleItems`/`resolveChildren` run the permission
  and role gate and the i18n route resolution at both levels, and drop a parent that has neither a
  route of its own nor a surviving child — a group emptied by permissions leads nowhere, so it
  stops rendering rather than presenting an empty expander.
- The local layer is a composable. `navItems.local.ts` exports `useLocalNavItems()` instead of a
  constant array, which is what lets a group's children come from a store rather than from a list
  written by hand. `NavItem` gained `params` (a child pointing at a parameterised route), `key`
  (several entries resolving the same route name need distinct keys) and `isLabelLiteral` (a row
  named by the person; running that name through `$t()` would translate a name that happened to
  match a key).
- `navGroupScroll.ts` — `getNavGroupScrollDelta` (pure geometry) and `revealNavGroup` (reads the
  DOM, scrolls the container). An opened group that unfolds below the fold of the rail's scroll
  area is a group nobody can read, and the page itself never scrolls, so the measurement is
  against the scroll container rather than `scrollIntoView`.
- `navBadges.local.ts` — the LOCAL extension point for the attention dot, so the synced navigation
  never imports a feature module. It asks this file; this file knows about the product.
- `TheNavbar.spec.ts` and `navGroupScroll.spec.ts`: the unit had no test of its own.

### Changed

- The rail collapses by animating a `grid-template-columns` track from `1fr` to `0fr` over
  `motion-calm` instead of unmounting `ONavbarDesktop`. The `v-if` was the largest layout shift in
  the app: the page jumped sideways on every toggle. The rail now stays mounted and goes `inert`.
- The rail's relief is no longer cut off at its own right edge. The `overflow-hidden` that makes
  the collapse possible clips at the wrapper's padding box, and the card's box IS that box — so
  only the left glow, which had the margin to fall into, ever showed. `-mx-3 px-3` gives the
  shadow room to paint inside the clip. Horizontal only: the rail is exactly `100dvh` tall, so
  vertical padding would clip its last item instead.
- The viewport comes from `useViewport` instead of a local width plus a resize listener. The same
  question and the same answer, from a media query rather than a measurement — the browser fires
  resize on every pixel of a window drag, and this only ever asked one yes-or-no question.
- One group is open at a time, and the choice is remembered: `expandedNavGroups` in `useUiStore`
  is passed to both the rail and the drawer and their `@toggle-group` comes back to
  `toggleNavGroup`, which is what keeps the two showing the same groups open across a reload.
- `MOBILE_ROUTE_NAMES` replaces the inline list inside `mobileItems`, and the bottom bar stays
  flat by construction: it filters the resolved top level only, so a route that exists solely as
  a child never surfaces there.

### Fixed

- Signing out empties the feature stores. Documented in the commit that made the change; recorded
  here because the call lives in this unit.

## 1.2.1 — 2026-07-15

### Changed

- Order navbar computeds per convention (ASC, dependency-first).

## 1.2.0 — 2026-07-13

### Added

- `personalizationWithRoute` computed and prop wiring to navbar organisms.

### Changed

- Global UI preferences moved to the Personalization page (navbar link only).

## 1.1.0 — 2026-06-13

### Added

- Brand reference nav item (superadmin-only).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
