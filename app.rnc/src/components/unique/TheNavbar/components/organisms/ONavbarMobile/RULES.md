---
version: 1.4.3
origin: pendulum
based-on: 1.4.3
---

# RULES — ONavbarMobile

> For AI agents. Last updated: 2026-09-17.

## Purpose

Fixed bottom navigation bar for mobile viewports (< md). Shows icon-only route links and a hamburger to open the drawer.

## Intentional Decisions

- **Two root elements: a backdrop div and the nav div**: the `mobile-navbar-backdrop` div sits behind the nav bar (z-40) to fill the safe area on iOS. The actual nav is z-50. Do not merge them into one.
- **The backdrop wears `canvas-atmosphere` as well as `bg-canvas` (CRITICAL)**: `bg-canvas` is the canvas COLOUR, which is the whole canvas only in a theme that paints nothing else. Under a theme with glows or a texture, a flat fill pinned over the page reads as a hole punched in it — this shipped as a hard-edged black band under the bar, exactly where the corner-bracket pattern stopped. See `styles/components/canvas-atmosphere.css`; the class costs nothing in a flat theme, where every layer defaults to none.
- **The bar mirrors TheLayout's rhythm, it does not invent one**: `barMaxWidthClass` and `barPaddingClass` are the `< md` half of TheLayout's `outerMaxWidthClass` / `contentPaddingClass`, read from the same `useUiStore().isFullscreen`. A fixed element is anchored to the window, so this is the only thing that puts the bar on the page column's edges — without it the surface ran past the cards by 16px on a phone and 70px at the tablet breakpoint. `mx-auto` is load-bearing: with `left-0` and `right-0` both set, auto inline margins are what let the cap centre the root. Order matters — the cap centres, then the padding insets, exactly as TheLayout builds the column. If TheLayout's padding or caps change, change these with them.
- **`rounded-xl` on all four corners**: the bar floats 16px off the floor, so square bottom corners read as a slab in the corner. `xl` (26px) is the radius scale's large-container step; do not drop to `lg` or reintroduce `rounded-t-*`.
- **The float gap and the safe area are one value, and it is a `max()` not a sum**: `pb-[var(--mobile-nav-gap)]` on the root, defined in `reset.css` as `max(1rem, env(safe-area-inset-bottom, 0px))`. Adding them is the bug — on a browser that reports no inset it is the 16px float, but on one that does (Firefox Android) the two STACK and the bar floats that much too high above an empty band. Do not move this back to a margin on the surface: the root's padding is what keeps the surface inside the padded box.
- **The backdrop height is `--mobile-nav-band`**, not the bar's own height and not a number written here. That variable is the ONE definition of the space this bar occupies, shared with TheLayout's scroller reserve. Shorter and the page shows through beside the inset bar; taller and the canvas paints over the last line of content. Anything else that pins itself to the bottom of the page uses the same variable rather than re-deriving it — a view that spelled the `calc()` out by hand drifted to a different height on any browser reporting a safe-area inset.
- **Global `<style>` (not scoped)**: hides both the backdrop and the nav bar when a PrimeVue Dialog mask is open (`body:has(.p-dialog-mask)`). This CSS must be global because PrimeVue appends the dialog mask to `<body>`, outside Vue's scoped shadow. Do not add `scoped`.
- **Icon-only links on mobile**: `RouterLink` items render only the icon `<span>`, not the label. The label prop is only used by desktop/drawer variants.
- **`exact-active-class="bg-primary-100"`** on RouterLink: active state is applied directly on the link element, not via `isExactActive` slot pattern.
- **`mobileItems` is a pre-filtered subset** passed from `TheNavbar` (`MOBILE_ROUTE_NAMES`) — only routes that belong in the mobile bottom bar. **There is no item cap**: the links live in their own `overflow-x-auto` box, so a list too long for the phone narrows that box and scrolls inside it, leaving the hamburger and the personalization link always reachable. The box must NOT take `flex-1` — stretching it to the leftover width pins the icons to the left against an empty right half; without it the parent's `justify-center` centres the whole bar and the shrink still yields the scroll.
- **`gap-3` between every control in the bar, and the scroller is `-m-1.5 p-1.5`.** The links carry `touch-target` (6px of growth on each side), so at the old `gap-1`/`gap-0.5` the grown areas overlapped and the later sibling won the tap (celer-06). The scroller is `overflow-x-auto`, which clips its content on BOTH axes: without its own 6px of padding a link's growth above and below was cut away and the link was 34px tall to a thumb. The negative margin gives that padding back, so the bar keeps its height and its outer spacing. `e2e/theme/touch-chrome.spec.ts` taps 4px past every edge and checks that no two areas overlap.

## Prop & Emit Contract

- `personalizationWithRoute`: icon-only link beside the hamburger button.
- `mobileItems`: pre-filtered nav items with resolved routes. Each item: `{ icon, label, route }`.
- `@toggle-drawer`: emitted when the hamburger is clicked. `TheNavbar` handles opening `ONavbarDrawer`.

## Do Not

- **Do not tighten the gaps below `gap-3`** or drop the scroller's `-m-1.5 p-1.5` — see above.

- **Do not write `min-[480px]:`** — the roomier layout from 480px is the named `xs:` breakpoint (`styles/tailwind.css`). An inline min-width variant is emitted before the named ones and loses to them silently.
- **Do not add `scoped` to the `<style>` block** — the `body:has(.p-dialog-mask)` selector must reach the document root.
- **Do not show nav labels on mobile** — icon-only is intentional for space constraints.
- **Do not increase `mobileItems` beyond 4** without updating the flex layout and breakpoint handling.
