# Changelog — TheLayout

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.3 — 2026-09-15

### Fixed

- Remove root `overflow-x-hidden`. It forced `overflow-y: auto` via CSS overflow pairing, which
  invented a second scroll container on the `h-full` root and showed thin vertical + horizontal
  bars that cut a few pixels on every authenticated page. Horizontal clip stays at the source
  (Navbar `lg:`-only negative margins), not on this wrapper.

## 1.3.2 — 2026-09-15

### Fixed

- Root wrapper adds `overflow-x-hidden` so a few pixels of child overflow cannot invent a document
  horizontal scrollbar on phone widths.

## 1.3.1 — 2026-09-14

### Changed

- RULES: `flex-1 min-h-0` on a view's root hides content unless the root holds its own scroller. The Idea views already carry the fix; the rule is what stops the next view from reintroducing it (from lifeshifter).

### Added

- `TheLocalOverlays.local.vue` — a LOCAL slot, empty in rnc, rendered after the module overlays. A project mounts a store-opened dialog of its own there instead of editing this file, which would conflict on every sync. The sync treats `*.local.vue` as LOCAL and scaffolds the stub when a project lacks it (from lifeshifter's proposal, which the project never applied).

## 1.3.0 — 2026-09-06

### Added

- `data-route-fade` on the content column. App.vue's route-fade scopes its opacity fade to this
  marker, so on a route change only the column breathes and the navigation rail beside it holds
  steady through the out-in swap — the rail still remounts, but it paints identical pixels in the
  same flush, so it reads as furniture that never left the room. The marker must stay on the
  column, never move to the root: on the root it would take the rail with it, which is the exact
  bug the scoped fade replaces.
- An `AMenuButton` for a page that supplies no header. The button is the ONLY way to reopen a
  collapsed rail and that state is persisted, so a headerless page used to strand the app with no
  route out but clearing localStorage.

### Changed

- Collapsing the rail hands its space to the content: the inner column's cap grows to the same
  width ladder the outer wrapper uses, animated with `transition-[max-width]` on the same
  `motion-calm`/`motion-ease-settle` pair as the rail's grid-track collapse. Before, the cap was
  static and the freed width was a dead band that read as the content sliding left.
- Both caps come from ONE ladder whose top step is 1448 — the 1200px reading column plus the
  rail's 248px footprint — instead of the 1536 a `2xl` breakpoint would allow. Two caps computed
  from different things, one from the rail and one from the breakpoint, only agreed by accident at
  1440; at 1536 an 1448 column left-aligned inside the wrapper stranded 88px and the page read as
  192px of margin on the left against 280 on the right.
- The narrow layout's padding steps up instead of being a flat `px-8`: on a 390px phone the flat
  value spent a sixth of the screen on margin and pushed the header row into two lines.
- The scroller's bottom reserve is `--mobile-nav-band` until `lg`, matching `ONavbarMobile`. It
  was `md`, which left 768–1023 with content sitting under the bar.

### Fixed

- The content column stopped short of the page bottom on any viewport under 800px tall, so a page
  whose columns fill the height ended on a different line than the navigation beside it.
  `max-h-[calc(120vh-10rem)]` was the cause: a cap that can only ever make the column shorter than
  the space it is given. The containment is `#app` at viewport height plus `flex-1` and `min-h-0`.
- The inner column dropped its `mx-auto`: the outer wrapper already centres the nav+content
  ensemble, and the second centring floated the column ~44px away from the rail where the rhythm
  says 32.
- The header wrapper dropped its `pb-2`: stacked on the scroller's `pt-8` it opened a 40px seam
  between the page header and the content.

## 1.2.0 — 2026-09-04

### Added

- Mounts `OIdeaCaptureDialog` and `OTipEditDialog` — the two module overlays that are opened from a
  store rather than from a screen, so they must exist above every route. Documented in RULES.md
  because a missing mount is silent: nothing renders, nothing throws.

## 1.1.1 — 2026-07-15

### Changed

- Order computed properties per convention (one-line before multi-line).

## 1.1.0 — 2026-07-13

### Added

- Reactive max-width and padding when `ui.store.isFullscreen` is enabled.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
