# Changelog — Theme System

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.6.0 — 2026-11-07

### Added

- `--motion-stagger` (55ms, 0 under reduced motion): the interval between one sibling's entrance and
  the next's — not a fifth duration, each sibling still takes `settle`. Read by
  `styles/components/motion.css` through `--reveal-index` (rnc-1).
- `text-brand-word`: the brand as a WORD on whatever ground the scheme gives it —
  `--color-primary-text` where the theme declares it, `primary-500` otherwise. The first template
  consumer of the token the preset already read; it replaces a `dark:` pair of ramp steps.

## 2.5.1 — 2026-09-22

### Changed

- `--color-primary-text` now answers for every place the preset writes the brand as a WORD on a dark
  ground: the outlined, text and link buttons, beside the focused float label of 2.5.0. Aura writes
  all of them in `{primary.color}`, and largatudoevai's outlined button fell to 4.00:1 on its dark
  canvas as soon as the owner moved the teal ramp down a step (2026-09-22). `battoni-dev` is
  unchanged — the fallback is still `primary-500`.

## 2.5.0 — 2026-09-22

### Added

- `--color-primary-text` (optional, dark block): the brand as a word on a dark ground. The preset's
  dark focused float label reads `var(--color-primary-text, var(--color-primary-500))`; it was Aura's
  `{primary.color}`, 4.38:1 on largatudoevai's dark panel. `_template.css` lists it.

### Fixed

- State labels (success, info, warn, danger, help — buttons, badges, the semantic contrast colour)
  read `ink-950` instead of the theme's `ink-900`: maresia's teal ink-900 measured 4.49:1 on
  `danger-500`, with the state palette unchanged. `battoni-dev`'s labels go from `#141413` to black
  (danger 4.90 → 5.58, help 4.66 → 5.31, info 5.01 → 5.71).
- `celer-tag-bold-primary` takes `--color-primary-contrast` instead of `ink-900`: 3.33:1 on the teal,
  5.07:1 with maresia's white. Unchanged in `battoni-dev`, whose contrast token is its ink.

All three measured by `e2e/theme/contrast.spec.ts`, which went red on largatudoevai's second
phase-2 sync while rnc passed.

## 2.4.2 — 2026-09-17

### Fixed

- **`celer-tag-soft-primary`** (`styles/components/tag.css`) wrote `primary-700` on `primary-100`, 1.83:1 in `battoni-dev`, in both schemes — the `primary` tone of every list organism's tag. It writes `primary-950`, 4.54:1, the step the preset already uses for brand words on the brand's tints. `e2e/theme/contrast.spec.ts` gained a case over every `celer-tag-*` helper and the default Tag, light and dark; it failed on this pair before the fix. `src/test/brandTintText.spec.ts` scans every class string for a lighter ramp step on a pale brand tint; it found the same 1.83:1 pair in `OListFeed` (1.3.2), the idea "planned" chip and `MPersonalizationVisualCard` (1.1.2), all now `primary-950`.

### Changed

- **Touch growth for default-size controls (VIS-18).** `styles/components/touch-target.css` grew only `.p-button-sm`; a default `.p-button` ("Sign in", "Continue", "Confirm") measured 42px tall under a finger, a Select or MultiSelect 40–42, a dialog's close button 40×40. Under `pointer: coarse` they now carry a transparent `::before` 2px past their border (`inset: -3px`, because an absolute box is placed from the padding box) — 44–46px to a thumb, drawn size unchanged, 4px still between two of them at `gap-2`. `e2e/theme/touch-target.spec.ts` measures the real controls on /components and a close-button probe; both failed before (42 and 40).

## 2.4.1 — 2026-09-17

### Fixed

- **SelectButton / ToggleButton options (VIS-07).** The unselected word read Aura's `surface-500` on `surface-100`, 2.63:1 (/news "Unread", /components "Tasks"), and its hover `surface-700`, 3.76:1. Light reads `--color-muted` (5.48) and hovers to `surface-900` (8.11), icon included. Dark unchanged.
- **Secondary text buttons (VIS-20).** Light read `surface-500` — 3.43 · 3.11 · 2.63 on canvas · panel · panel-muted (the comment bin on /ideas/1). It reads `--color-muted` now (7.15 · 6.49 · 5.48). In dark the word stays `surface-400` and the ground it is hovered and pressed on moves from `surface-800`/`-700` (3.77 / 2.58) to `surface-950`/`-900` (8.00 / 5.58).
- **Default Tag.** The preset set `tag.root.*`, which nothing reads; `.p-tag` is painted from `tag.primary.*`, so a `rounded` default Tag (which `tag.css` does not match) showed Aura's `primary-600` on `primary-100`, 1.83:1. Light now sets `tag.primary` to the pair `tag.css` paints — ink on `primary-50`, 17.98 — and the dead `root` blocks are gone from both schemes. Ink rather than `{primary.contrast.color}`: that token is the label of the 500 fill, not of a pale tint.
- **The mobile drawer as a ground.** Closed by `ONavbarDrawer` (container `bg-canvas`); the Known Gap is retired.

### Added

- `e2e/theme/contrast-controls.spec.ts` measures the three controls above in both schemes.
- RULES: "Controls the preset colours outside the text tiers" (the table), and "a pale tint takes ink, not the contrast token". The line saying the default Tag's preset pair never renders is retired.

## 2.4.0 — 2026-09-17

### Fixed

- **Filled-button labels below 4.5:1 (VIS-01, lifeshifter-580).** The preset put white on every state fill — 2.28:1 on `success-500`, 2.15:1 on `warn-500`, 3.68:1 on `info-500`, 3.76:1 on `danger-500`, 3.96:1 on `help-500`, and 1.80:1 on the `info-300` dark presses to. Labels are now chosen per state and per scheme (table in RULES): light takes ink where the fill is pale and white where it is deep; dark takes ink on all three steps of the four state palettes. Fills are unchanged.
- **The brand ramp written as text (VIS-02, VIS-03).** The outlined, text and link buttons (`primary-500`, 1.51:1 on white), the active tab (`primary-700`, 1.93:1) and the highlighted option (`primary-700`/`-800`, 1.88 and 2.41 on its tint) read `primary-950` in light: 4.78 on white, 4.66 and 4.54 on the tints. The panel stays under the floor (4.34) — Known Gap.
- **Float label holding a value, or focused (VIS-06).** `floatLabelActiveColor` reads `--color-muted` (Aura's `surface-500` was 3.11:1 on the panel) and `floatLabelFocusColor` reads `--color-heading` (Aura's `primary-600` was 1.69:1 on white).
- **Badges (VIS-05, rnc-192).** A global `.p-badge` rule in `styles/libraries/primevue.css` painted every badge lime with a literal white label (1.51:1), whatever its severity — its `data-pc-section` guards never matched in PrimeVue 4. Removed; the preset's badge takes the brand fill with the brand contrast token by default, ink on the four state fills, and ink on the pale secondary chip (`surface-700` there was 3.34:1).
- **`celer-button-*` helpers (VIS-08, ltv-071, lifeshifter-499/500/579, rnc-259).** The filled helpers read the preset's `--p-button-*` variables instead of naming their own labels (white on `warn-500` 2.15:1, on `danger-500` 3.76:1, on `surface-500` 3.43:1; `ink-900` on the brand fill instead of `--color-primary-contrast`). `celer-button-secondary` therefore renders as the preset's secondary (light grey in light, `surface-800` in dark) instead of a mid-grey. The text and link secondary helpers read `--color-muted` in both schemes (`surface-600` was 4.09:1 on white); the link primary helper reads `primary-950`.
- **Scrollbars (ltv-073, rnc-090, lifeshifter-439).** `scrollbar-color` had one value, which is invalid, so neither Firefox nor Chrome 121+ drew the themed bar. It is thumb `line-strong` over a `canvas` track now, and the WebKit track matches. `.scrollbar-brand`, used by the rail and the drawer, is defined (it was defined nowhere).
- **`#app` is `100dvh` (lifeshifter-440).** `100%` followed the tall layout viewport on a phone, so the column overran the visible screen and the document scrolled behind it.
- **Touch target on icon-only small buttons (lifeshifter-462/603).** The hit area moved from `::after` to `::before`: taking over PrimeVue's `::after` spacer made an icon-only small button 7px shorter under a finger than under a mouse (32×28 against 32×35).

### Changed

- RULES: "green reads well in both schemes" retired — the ramp is a fill, not a word. "What sits ON a fill" rewritten with per-state, per-scheme measurements. Known Gaps added (brand text on the panel; the drawer as a ground).

## 2.3.0 — 2026-09-16

### Fixed

- **Text contrast below the declared floor (from lifeshifter).** In `battoni-dev` light, `--color-muted` (`surface-600`) measured 4.09 · 3.71 · 3.13 on canvas · panel · panel-muted, and `--color-subtle` (`surface-400`) 1.90 · 1.72 · 1.45, against a 4.5:1 floor. `muted` is `surface-800` now (7.15 · 6.49 · 5.48) and `subtle` is `surface-600` (4.09 · 3.71 · 3.13). `surface-700` was rejected for `muted`: 4.44 on the panel, 3.76 on panel-muted. Dark was already inside both floors and is unchanged (muted 5.78 · 5.38 · 5.03, subtle 3.90 · 3.63 · 3.40).
- The PrimeVue preset (light) painted secondary text, placeholders, float labels and field icons from Aura's `surface-500`/`-400` (3.43:1 and 1.90:1 on white). `text.mutedColor`, `formField.placeholderColor` and `formField.floatLabelColor` read `var(--color-muted)`, `formField.iconColor` reads `var(--color-subtle)`. Dark keeps Aura's values, which clear both floors.
- The Editor placeholder (`styles/components/editor.css`) reads `--color-muted`: a placeholder is text.
- `_template.css` suggests `surface-800` for `muted` (was 700, which misses the panel on this ramp), says both floors and the three grounds to measure on, and its step 5 no longer tells a fork to edit `colors.css` by hand.

### Added

- RULES: "`muted` reads, `subtle` does not" — `subtle` never carries a sentence and is for icons and marks, with a 3:1 floor (WCAG 1.4.11); `muted` carries every hint, timestamp, subtitle and placeholder at 4.5:1. The measured before/after table. `e2e/theme/contrast.spec.ts` measures both tiers and PrimeVue's copies on all three grounds in both schemes.
- RULES: **deriving a theme** (from chapaja). A theme may `@import` a sibling and override its brand ramps, `--color-primary-contrast`, fonts, radius, shadows/relief, atmosphere and the dark role values; it must not change the state palettes, the role vocabulary or the light role mapping, restyle the base's selectors, or chain imports. Verified through `@ActiveTheme` with a throwaway derived theme: the relative import resolves, the second `@theme` block wins with one emitted value, and the base's light role tokens follow the new ramp. Copying `_template.css` stays the path for a theme that changes the role tokens.

### Changed

- RULES: the role table says `subtle` is "icons and marks — never text" (was "faint text, icons, placeholders"). Retired, with its reason: "hint text is `text-muted` to `text-subtle`".
- Preset: the secondary button label was `surface.600` on its `surface.100` fill (3.13:1, light) and a pressed dark button put the white label on `surface.600` (4.09:1). Light labels are `surface.900`/`950`, the dark pressed fill is `surface.900`; `e2e/theme/contrast.spec.ts` measures all three states in both schemes.

## 2.2.0 — 2026-09-14

### Added

- `--color-primary-button` — an optional fill for filled brand buttons, read by the preset with the brand fill as fallback, for a theme whose label cannot clear 4.5:1 on `primary-500` (from lifeshifter).

### Fixed

- The `secondary` Tag was under the 4.5:1 floor in both schemes (3.13:1 light, 4.29:1 dark in `battoni-dev`). Its text is `--color-body` now: 14.13:1 and 5.06:1 (from lifeshifter, re-measured here).
- **Typography belonged to the theme on paper only.** The preset and the reset named Space Grotesk and Sometype Mono and put every `<p>` in mono, so another theme's `--font-sans` was loaded and never used. Both read the tokens now; `battoni-dev` keeps mono prose by declaring it in its own file, so this theme looks the same (from lifeshifter).
- The Divider was painted in the brand colour; it is `--color-line` (from lifeshifter).
- The FloatLabel `on` variant had no plate in light and rendered as a white pill; it is `--color-panel` (from lifeshifter).
- In dark the brand button walked the ramp UP on hover and press (400, 300); it darkens to 600/700 as light does (from lifeshifter).
- Toasts: radius, border and shadow moved to `.p-toast-message`; the fill on `.p-toast-message-content` covered the rounded corners with a square slab. A toast is a panel with a 4px severity bar and the large relief (from lifeshifter).
- `celer-button-*` lived twice with `!important` (`libraries/primevue.css` and `components/button.css`); only `button.css` keeps them, and three classes with no call site left (`text-info`, `text-warn`, `text-danger`) (from lifeshifter).
- Quill's stylesheet was never imported, so the Editor toolbar rendered empty buttons; `main.css` imports it before the project's overrides (from lifeshifter).
- `_template.css` starts a new theme with `--color-muted` on `surface-700` and `--color-subtle` on `surface-600` (were 600 and 400). A form label is normal text and needs 4.5:1 on the canvas; the template handed every fork a label that failed it (from new.impressao).

### Changed

- RULES: two relief traps (color-mix in a scheme block, a rule is ink at low alpha), never animate a property that carries a datum, and the fork steps — `VITE_ACTIVE_THEME` instead of hand-editing `colors.css` and `ACTIVE_THEME`, `@fontsource` preferred (from lifeshifter).

## 2.1.0 — 2026-09-09

### Changed

- The page atmosphere is published as `--canvas-atmosphere`, `--canvas-overlay` and `--canvas-overlay-opacity`, with `body` painting through them. `--atmo-*` stays private to the theme. A theme that sets none of the three keeps a flat canvas, which is the correct default — documented in `_template.css`.

## 2.0.0 — 2026-09-06

### Added

- **What sits ON a fill.** `--color-primary-contrast`, the trap that it resolves from
  `tailwindcss-primeui` even when a theme is silent, and the measured label-vs-fill table for
  `battoni-dev` across rest, hover and active. Measured here, not copied: a lime fill takes ink at
  12.22:1 where white is 1.51:1, which is the inverse of a theme with a dark accent.
- **The relief vocabulary**, and why dark cannot copy light — `--relief-lift` follows `--shadow-*`
  on its own while the two insets do not, because an inset is a light source rather than a drop
  shadow.
- **Motion**: the four durations, the curve, and the rule that nothing a person can fire twice a
  second may reach `settle`. Plus why reduced motion collapses to 90ms and not to zero.
- **Typography belongs to the theme**, and **the contrast floor is 4.5:1 and is tested** — with
  where the test lives and why it cannot be a unit test.

### Changed

- Major, because a theme that satisfied 1.x can fail this file: four relief tokens and
  `--color-primary-contrast` are now REQUIRED of every theme, and `_template.css` says so. A theme
  that omits them is not broken on screen, which is exactly why the requirement had to be written
  down and tested rather than left to be noticed.

## 3.0.0 — 2026-06-13

### Changed

- Replace the flat theme with the atmospheric battoni-dev identity (light+dark), env-driven active-theme selection, and scheme-flipping role tokens.

## 2.0.0 — 2026-06-10

### Added

- **Multi-theme architecture.** `colors.css` is now a thin active-theme selector that
  `@import`s one file from `themes/`. Added `themes/battoni-dev.css` (default) and
  `themes/_template.css` for new themes.
- **Light/dark mode.** Role tokens (`canvas`, `panel`, `panel-muted`, `heading`, `body`,
  `muted`, `subtle`, `line`, `line-strong`) flip under `[data-theme='dark']`. Runtime
  toggle via `colorScheme.store.ts` (persisted, system default) + anti-FOUC inline script.
- PrimeVue `semantic.colorScheme.dark` and per-component dark blocks; `darkModeSelector`
  set to `[data-theme="dark"]`.

### Changed

- **BREAKING:** components must use **role tokens** for structure instead of raw
  `surface-*` / `ink-*` shades. Migration map documented in RULES.md.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
