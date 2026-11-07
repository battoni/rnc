# Changelog — ATip

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.0.2 — 2026-09-17

### Fixed

- The trigger was 32×32 on a touch screen; it grows to 44px with `pointer-coarse:size-11` (tip-014).
- A link in the tip navigated in the same tab, off the screen the help belongs to. The body renders through `withExternalLinks` (tip-002, from new.impressao 03c4a47). Covered by `ATip.touch-and-links.spec.ts`.

## 2.0.1 — 2026-09-04

### Fixed

- `break-words` on the tip body, so a long unbreakable token cannot overflow the popover.

## 2.0.0 — 2026-09-04

### Changed

- **BREAKING**: `content` prop and default slot replaced by a required `tipKey` anchor. The text
  now comes from the database through the tip store, so it can be edited without a deploy.
- Body is rendered as sanitised HTML (prose styling) instead of plain-text paragraphs.
- Renders nothing when the anchor carries no tip.

### Added

- Pencil affordance inside the popover for `tips.manage`, routed to the single `OTipEditDialog`
  through `tipStore.requestEdit`.

## 1.0.1 — 2026-07-10

### Fixed

- Use kebab-case `aria-controls`, `aria-expanded`, and `aria-label` on the trigger so screen readers associate the button with the popover.

## 1.0.0 — 2026-07-07

### Added

- Initial `ATip` atom: info icon trigger, dismissable popover, `content` prop + default slot.
- App-wide single-open coordination via `useSingleTip` composable.
- Unit tests (`ATip.spec.ts`).
