---
version: 1.8.0
origin: pendulum
based-on: 1.6.3
---

# RULES — TheNavbar

> For AI agents. Last updated: 2026-09-17.

## Purpose

Orchestrator for all three navbar variants (desktop sidebar, tablet/mobile drawer, mobile bottom bar). Owns navigation state, auth logic, and permission/role filtering.

## Intentional Decisions

- **`CORE_NAV_ITEMS` and `settings` are defined here, not in a constants file.** The core list is a plain constant; `itemsWithRoutes` composes it with the local layer on every read, which is what keeps a store-backed group live.
- **The local layer is a composable, not a constant.** `navItems.local.ts` exports `useLocalNavItems(): ComputedRef<NavItem[]>`, called once in setup, so a group's children can come from a store — a group whose rows are records the person owns — instead of from a hand-written list. It may load what its items need; keep it cheap, because it runs on every navigation.
- **Three optional `NavItem` fields exist for that case, and only that case**: `params` (a child pointing at a parameterised route), `key` (several entries resolving the same route name need distinct keys), and `isLabelLiteral` (an entry named by a person's own record — running a trail's name through `$t()` would translate a name that happened to match a key). Do not reach for them for a static entry.
- **Home route special-cased**: `resolveRoute` uses `{ name: 'home' }` directly for the home item instead of resolving via `getI18nRouteName`. All other routes go through the helper.
- **The declared shape decides the behavior — nothing is configured twice.** A `NavItem` with a `routeName` and no `children` is a plain link; `children` and no `routeName` is an expander; both is a link whose text navigates while a chevron toggles. There is no `type` or `isGroup` flag to keep in sync with the data, and there must never be one — see `interfaces.ts` for the contract and `navItems.local.ts` for a worked example of all three.
- **Children are one level deep and links only.** `resolveChildren` drops a child with no `routeName`; nesting a group inside a group is not supported and must not be added without a product decision.
- **Both levels go through the same gate.** `isVisibleNavItem` (permission + role) runs on parents and children alike, and `toVisibleItems` drops a parent that has no route of its own and no surviving children — a group that was emptied by permissions leads nowhere, so it never renders.
- **`key` is `key ?? routeName ?? label`, and it is also the persisted group key.** The i18n label is not unique across projects (`navItems.local.ts` is a documented extension point), so an item that declares a route keys on it. Renaming a route or a label resets that group's remembered state — acceptable, and cheaper than a hand-maintained id.
- **Expanded/collapsed lives in `useUiStore`, not here.** `expandedNavGroups` + `toggleNavGroup` are passed down and back up as one prop and one event, which is what keeps the rail and the drawer showing the same groups open and what survives a reload. The store keeps **one group open at a time** — opening a group closes whichever was open, in the rail and the drawer alike. The prop stays a `string[]`; it simply never holds two.
- **Revealing an opened group is the organisms' job, not this one's.** `navGroupScroll.ts` holds the shared geometry (`getNavGroupScrollDelta`) and the DOM step (`revealNavGroup`); both organisms call it against their own scroll container. Nothing here measures anything.
- **The rail collapses as a grid track, not a `v-if`**: the root is a one-column grid animating `1fr → 0fr` over `motion-calm`. Unmounting `ONavbarDesktop` jumped the whole page sideways, and `width` cannot transition out of `auto`; on an intrinsically sized grid container a `1fr` track resolves to the rail's max-content width, so nothing is hard-coded. The rail stays mounted, clipped by `overflow-hidden`, faded, and `inert`. **`-mx-3 px-3` is `lg:`-only** — below `lg` the track is `w-0` and those negative margins still spilled past the viewport, buying horizontal (+ vertical) scroll on every phone page.
- **`railInert` returns `true | undefined`, never `false`**: `:inert="false"` ships as `inert="false"`, and HTML reads any present `inert` attribute as true. Absent is the only "off".
- _Retired 07/11/2026 (rnc-1 F1):_ the Design group (`DESIGN_NAV_GROUP`, `design.read`) and the News, Ideas and Tips settings entries — the Showcase, Idea and Tip modules they opened were removed. The hub's own groups (Equipe · Reinaldo · Operador) come from `navItems.local.ts`, gated by permission.
- **`mobileItems` is an allow-list, and the bottom bar stays flat**: `MOBILE_ROUTE_NAMES` hard-codes which route names reach the bottom bar. Children never surface there — a route that exists only as a child of a group does not appear on mobile even when it is on the list. Add a route name to `MOBILE_ROUTE_NAMES` when a new primary route should appear on mobile.
- **Logout navigates to `/login` (literal path), not a named route**: this is intentional to avoid i18n route resolution on logout.
- **`personalizationWithRoute` computed**: resolves localized `/personalization` path and is passed to all navbar organisms. Dark mode is **not** toggled in the navbar.
- **`drawerClass` / `drawerPosition` are computed and passed to `ONavbarDrawer`**: mobile uses `position='full'` and class `'nav-drawer-mobile'`; tablet uses `position='left'` and class `'w-54'`.

- **The brand block is `MNavbarBrand`** (rnc-4 R10, `components/molecules/`): the host firm's tile and
  logo text and the product's name (`platform.name`), from `useFirmBrand` — the same block at the top
  of the rail and of the drawer. It renders nothing until a firm is known (the tab's last answer or the
  API's), so no name the build carries ever shows. A molecule that reads a composable, not a prop: the
  rail and the drawer are presentational and the firm is the app's, not the navigation's.

## Prop & Emit Contract

No props or emits — this is a unique layout component.

## Dependencies & Context

- **`useUiStore`**: `isSidenavOpen` / `toggleSidenav` / `closeSidenav` drive drawer open state across the app.
- **`useUserStore`**: `hasPermission()` filters nav items; `user.role_slug` gates role-restricted items; `clearUser()` is called on logout.
- **`LogoutService`** from `@AuthModule`: called on logout. Success and HTTP 401 both run `completeLogout` (clear user, `resetAllStores`, login path, success toast) — a 401 means the server already has no session. Other failures show an error toast.
- **`::deep(.nav-drawer-mobile)`** in this component's `<style scoped>` forces `height: 100vh` on the mobile drawer — this CSS targets `ONavbarDrawer`'s Drawer root through the scoped boundary.

## Do Not

- **Do not add navigation items directly to `ONavbarDesktop` / `ONavbarDrawer`** — all items are managed here and passed down.
- **Do not restructure `navItems.local.ts` into groups on a whim** — the file is LOCAL (the sync never touches it) and its grouping is a product decision, not a demo. Its doc comment shows the three shapes; the item list itself stays flat until someone decides otherwise.
- **Do not filter or resolve children in an organism** — both organisms receive `NavRouteItem[]` already gated and resolved, and must stay presentational.
