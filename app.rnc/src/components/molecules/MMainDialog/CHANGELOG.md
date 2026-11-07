# Changelog — MMainDialog

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.3 — 2026-09-17

### Fixed

- The default footer's buttons are `gap-3` apart instead of `gap-2` (celer-06, 12px between
  controls).
- The close button grows to 44px under a finger (`pt:pcCloseButton:root:class`); it was 40px.
- The content takes `.scrollbar-brand` through `pt:content`; the component's private copy of the
  same scrollbar rules is gone.
- `RULES.md` and the phone media-query comment describe what the code does: `appendTo` stays `self`
  and the close control stays at `right-2.5`. The lines claiming `appendTo` is `body` below `md` and
  that the close control moves to `right-4` are retired; porting the switch is a Known Gap.
- `MMainDialog.spec.ts`: the cancel and submit cases no longer skip their assertion when the button
  is missing.

## 1.2.2 — 2026-09-15

### Fixed

- Footer (`.p-dialog-footer`) uses `--color-canvas` again, matching the mask and sheet body. It had
  been set to `--color-panel` under a wrong "overlays are panels" rule; in dark scheme that painted
  a grey bar (`#141413`) under the near-black body (`#0a0a09`) on every MMainDialog (Users, Tips,
  Permissions, Showcase).

## 1.2.1 — 2026-09-14

### Fixed

- `dismissableMask` defaulted to `true` and did nothing: PrimeVue closes on an outside tap only when `dismissableMask && modal`, and `modal` is `false` here by design. No dialog ever closed that way. The default is `false` now, and the RULES line that promised the behaviour says what actually happens (from lifeshifter).

## 1.2.0 — 2026-09-06

### Fixed

- The mask paints its gutters. The sheet does not fill the mask horizontally and the mask painted
  nothing, so the two 32px gutters were a hole straight through to the page and the cards behind
  slid down them as a rounded edge every time a dialog opened. It is worst between 768 and 1023,
  where the page's own padding (24px) and the sheet's margin (32px) disagree.
- Canvas, not a scrim: this mask is scoped to `<main>`, so it ends exactly where the page header
  begins and a dark frame would end in a hard line under the page title. The gutters are the
  page's own ground, so they are painted as it.
- The fill animates as `background-color`, not as the mask's opacity — opacity would take the
  sheet with it — on the same `--motion-settle` pair the sheet uses, so reduced motion reaches it.
- Below `md` the sheet is the whole screen and square, because a rounded corner against the screen
  edge reads as a notch. There are no gutters left to paint there.

Measured in `e2e/theme/dialog-mask.spec.ts` at 390, 768, 1024 and 1440: a mask and a sheet only
have positions in a real browser, so this cannot be a unit test.

## 1.2.0 — 2026-06-23

### Fixed

- Footer (`.p-dialog-footer`) now takes the body tone (`--color-canvas`) so the seam disappears, especially in dark scheme.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`MMainDialog.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
