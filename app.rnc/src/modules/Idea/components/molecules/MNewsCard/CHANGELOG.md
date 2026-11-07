# Changelog — MNewsCard

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.4 — 2026-09-17

### Removed

- `overflowTarget`, a ref written on every menu open and never read, with the comment that said the confirmation anchors to the `⋮` — the code anchors to the row. The RULES bullet saying the same is retired, and the method comment no longer cites `WeekView`, a view this repository does not have (from lifeshifter-489).

## 1.1.3 — 2026-09-16

### Changed

- The spec's entry fixture uses a numeric id (from lifeshifter-269).

### Fixed

- The date line and the credit: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.1.2 — 2026-09-15

### Fixed

- Management controls take a full footer row (`w-full basis-full justify-between`): applause on the
  first line; eye left and `⋮` right on the second at every width. Sharing a row with reactions (or
  clustering at the start after wrap) left the tools looking stuck to the left on phone.

## 1.1.1 — 2026-09-10

### Fixed

- Headline and standfirst wrap (`wrap-anywhere`). A title with no space and no hyphen did not
  break at all: measured, the headline's box was 439px and its content 2770px, and the overspill
  painted across the card beside it. Nothing moved, so nothing looked wrong.

## 1.1.0 — 2026-09-09

### Changed

- Optional `reactionsDisabled` disables applause while the view cools down after a 429.

### Added

- Spec covers `reactionsDisabled` disabling `MNewsReactions`.

## 1.0.0 — 2026-09-01

### Added

- First release, extracted from `NewsView` so each card can own its own overflow `Popover` — a
  `v-for` in the view cannot hand one component instance a template ref per card.
- The applause moved onto the card: somebody scrolling the changelog can now clap without opening
  the entry. It sits OUTSIDE the button that opens the article, which is the only arrangement that
  is valid HTML and the only one where the click does what it says.
- The three write actions moved behind one `⋮`, the pattern `ThePageHeader` already uses. The read
  count stayed on the card — it is information, not an action.
- A cover that fails to load now costs the image box and nothing else. It used to render its `alt`,
  the entry's title, as bare text sprawling outside the card.
