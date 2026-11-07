---
version: 2.5.1
origin: pendulum
based-on: 2.5.1
---

# Theme System

A **multi-theme**, **light/dark** token system. One active brand theme is chosen at
build time; the user toggles light/dark at runtime. Components consume **role tokens**
(structure) and **state palettes** (meaning) — never raw brand ramps — so they retheme
and flip schemes with zero per-component edits.

---

## Layout

```text
styles/theme/
  colors.css            ← ACTIVE-THEME SELECTOR: a single @import of one themes/*.css
  fonts.css             ← @font-face declarations (WOFF2 in public/fonts/)
  themes/
    battoni-dev.css     ← the default theme (palette ramps + role tokens + dark overrides)
    _template.css       ← copy this to start a theme that changes the role tokens
    <brand>.css         ← or @import a sibling theme and override its brand (see the end)
```

## Three token tiers

1. **Brand palette ramps** — `primary-*`, `ink-*`, `surface-*` (50→950). Per theme. Fixed
   light→dark scales; they do **not** flip by scheme. Raw material only.
2. **Role tokens** — the layer components use for **structure**. They flip per scheme:

   | Token / utility                       | Use for                        |
   | ------------------------------------- | ------------------------------ |
   | `canvas` (`bg-canvas`)                | page background                |
   | `panel` (`bg-panel`)                  | cards, panels, raised surfaces |
   | `panel-muted` (`bg-panel-muted`)      | hover / subtle fills           |
   | `heading` (`text-heading`)            | titles, strong headings        |
   | `body` (`text-body`)                  | default body text              |
   | `muted` (`text-muted`)                | labels, secondary text, hints  |
   | `subtle` (`text-subtle`)              | icons and marks — never text   |
   | `line` (`border-line`, `divide-line`) | default borders / dividers     |
   | `line-strong` (`border-line-strong`)  | emphasized borders             |

3. **State palettes** — `success`, `info`, `warn`, `danger`, `help`, `contrast` (50→950).
   Brand-agnostic, identical across themes, same in light and dark. Use for status/meaning.

**Brand accents** (`primary-*`) stay literal, and **the ramp is a FILL, not a word.** In
`battoni-dev` it is a lime: on white `primary-500` is 1.51:1, `700` 1.93:1 and even `950`, the
darkest step, only 4.78:1 — and on the panel no step clears 4.5:1 at all (950 is 4.34). A fill, an
icon on a dark ground, a bar, a highlight behind a word: yes. The word itself: in light, only
`primary-950`, and only on the canvas or the brand's own pale tints (see Known Gaps).

**Retired:** "green reads well in both schemes". It read well as a fill and was taken as a licence
for text, and the preset, the helpers and a dozen screens wrote words in it at 1.2–1.9:1.

---

## How light/dark works

- Role tokens are declared in each theme's `@theme` block with **light** values, then
  overridden under `[data-theme='dark']`. They're CSS custom properties, so the same
  utility (`bg-panel`) resolves to the dark value automatically — **no `dark:` variants**.
- The scheme is set on `<html data-theme="light|dark">` by `colorScheme.store.ts`
  (persisted to `localStorage`, defaults to system). An inline script in `index.html`
  sets it before paint to avoid a flash.
- PrimeVue: `darkModeSelector: '[data-theme="dark"]'`; the preset (`libraries/primevue/theme.ts`)
  defines `semantic.colorScheme.dark` (an inverted surface scale) and `dark` blocks per
  component. Component overrides reference tokens, so most dark blocks mirror light.

---

## Contributor conventions

- **Structure → role tokens.** Backgrounds (`bg-canvas`/`bg-panel`), text
  (`text-heading`/`text-body`/`text-muted`), icons and marks (`text-subtle`), borders
  (`border-line`/`border-line-strong`). These flip for dark.
- **Meaning → state palettes.** `bg-success-100`, `text-danger-700`, etc.
- **Brand action → `primary-*`.**
- **Never** use raw `surface-*` / `ink-*` shades in components — they don't flip; use the
  role token that maps to them.
- **Never** use `bg-white` / `text-black` / hex literals — use `bg-canvas` / role tokens.
- **Never** use Tailwind built-in palettes (`gray`, `sky`, `emerald`, `red`, `slate`, …).

---

## What sits ON a fill (CRITICAL)

A saturated fill is not a role token and does not flip, so nothing about the surrounding scheme
tells you what colour its label should be. **The fill decides, and the fill is measured.** A label
on a filled control is normal text — PrimeVue renders a button at 1rem/500 — so the floor is
**4.5:1**, never the 3:1 that applies to a graphic.

**The brand fill takes `--color-primary-contrast`.** Consume it as `text-primary-contrast` in a
template, or as `{primary.contrast.color}` in the PrimeVue preset — **never** as a raw
`text-ink-*` or a literal white. Which one reads is a property of the active brand's fill, and a
component cannot know it.

That token has a trap worth knowing: `tailwindcss-primeui` maps the same name to
`--p-primary-contrast-color`, so `text-primary-contrast` resolves even when the theme is silent —
answering from the PrimeVue preset instead of from the brand. A theme that does not declare it is
not broken on screen, it has simply lost the argument, and there is no error anywhere. **Every
theme declares it**, and `e2e/theme/contrast.spec.ts` compares the resolved value against the
theme's own ink so that silence is caught.

**The label is chosen per STATE, not per palette.** Light walks the state ramps down
(500 → 600 → 700) and dark walks them up (500 → 400 → 300), so the same palette name sits on three
different fills in each scheme, and on several of them no single colour reads on all three.

Measured in `battoni-dev`, label on fill, rest · hover · active:

| palette    | light fills     | light label          | light ratios         | dark fills      | dark label | dark ratios          |
| ---------- | --------------- | -------------------- | -------------------- | --------------- | ---------- | -------------------- |
| `primary`  | 500 · 600 · 700 | contrast token (ink) | 12.22 · 10.89 · 9.56 | same            | same       | same                 |
| `success`  | 500 · 600 · 700 | ink · ink · white    | 8.09 · 5.59 · 5.02   | 500 · 400 · 300 | ink        | 8.09 · 10.58 · 13.13 |
| `info`     | 500 · 600 · 700 | ink · white · white  | 5.01 · 5.17 · 6.70   | 500 · 400 · 300 | ink        | 5.01 · 7.25 · 10.22  |
| `warn`     | 500 · 600 · 700 | ink · ink · white    | 8.58 · 5.79 · 5.02   | 500 · 400 · 300 | ink        | 8.58 · 11.04 · 12.78 |
| `danger`   | 500 · 600 · 700 | ink · white · white  | 4.90 · 4.83 · 6.47   | 500 · 400 · 300 | ink        | 4.90 · 6.66 · 9.71   |
| `help`     | 500 · 600 · 700 | ink · white · white  | 4.66 · 5.38 · 6.98   | same as light   | same       | same                 |
| `contrast` | 500 · 600 · 700 | white                | 4.74 · 7.81 · 10.37  | same as light   | same       | same                 |

"Ink" is `{ink.900}`. The semantic `{success,info,warn,danger}.contrast.color` is ink in both
schemes — it answers for the `500` step it is named after — and the button's `hoverColor` and
`activeColor` say white where the fill has gone deep enough to need it.

Three things that table is telling you:

- **The brand ramp is the comfortable one here, and that is a property of THIS brand.** A lime
  fill takes ink at 12.22:1; white on it is 1.51:1, which is not "worse" but unreadable. A theme
  with a dark accent inverts every line of this table, which is why the answer is a token.
- **A label may change as the fill deepens, and that is the measurement, not a flourish.** White
  was the label for every state palette: 2.28:1 on `success-500`, 2.15:1 on `warn-500`, 3.68:1 on
  `info-500`, 3.76:1 on `danger-500`, 3.96:1 on `help-500` — and in dark, where the ramps lighten,
  1.80:1 on `info-300`. Ink on `success-700`/`warn-700` is 3.67–3.68, and white there is 5.02.
- **The same table governs everything that paints a label on these fills**: the Badge
  (`success`/`info`/`warn`/`danger` take ink on `500`) and the `celer-button-*` helpers, which read
  the preset's `--p-button-*` variables instead of naming a colour.

**Retired:** the previous table, which gave one label per palette ("success · ink-900", "info ·
white") and filed the misses under "fill problems … no label colour fixes them". The preset did not
follow it — it painted white on all four — and white does fix `700`.

---

## The relief vocabulary (CRITICAL)

Four tokens that carry **elevation** rather than colour: `--relief-lift`, `--relief-lift-lg`,
`--relief-sink` and `--relief-edge-top`. A component asks for `shadow-lift` and the theme answers.

**They are hand-written `@utility` declarations in `tailwind.css`, not `--shadow-*` entries in
`@theme`, and that is not a style choice.** Tailwind generates a utility from a theme token by
INLINING the token's value, so `.shadow-lift` would carry the light scheme's literals and never
read the property at all — every dark override would be dead code and dark surfaces would wear
light's geometry with only the colours swapped. The name is `--relief-*` precisely so Tailwind
does not claim the `shadow` namespace and put the inlined copy back.

**Every theme must define all four.** An undefined `var()` resolves to nothing and the property
falls back to its initial value, so a theme that forgets one ships an app whose raised surfaces
are silently flat. This is not hypothetical: `shadow-lift` was worn by fourteen elements here and
`shadow-sink` by three, for two weeks, with the utilities undeclared and nothing to report it.
`_template.css` marks them REQUIRED and `e2e/theme/relief.spec.ts` reads the computed shadow.

### Why dark cannot copy light

`--relief-lift` points at `--shadow-md`, which the dark scheme already redefines, so it follows on
its own. The two **insets** do not, because an inset is a light source rather than a drop shadow:
a 50% white top edge is a value of the light scheme, and on a dark panel it is a hairline of
glare. Dark redefines `--relief-edge-top` and `--relief-sink`, and nothing else.

Two traps that cost real debugging:

- **Never write `color-mix(… var(--x) …)` inside a scheme block for a variable the scheme
  redefines.** Lightning CSS resolves the `var()` against the ROOT scope when it bakes the
  no-color-mix fallback, so a mix written in the dark block ships carrying the LIGHT value. Use a
  literal `rgb()` there.
- **A rule is ink at low alpha, never "the shadow colour".** That token's meaning inverts with the
  scheme, and a near-black rule on a near-black panel measures ΔE 2.2 — invisible.

---

## Motion (CRITICAL)

Four durations and two curves, declared in `tailwind.css` rather than in a theme, because **how
long a thing takes is not a skin**: light and dark move the same way, and a theme that forgot to
define them would silently ship an app with no motion.

| token              | value | for                                                                   |
| ------------------ | ----- | --------------------------------------------------------------------- |
| `--motion-instant` | 90ms  | something that must look like it was already true: hover, focus, tint |
| `--motion-quick`   | 180ms | something the pointer just caused                                     |
| `--motion-calm`    | 320ms | something that enters or leaves on its own: a panel, an empty state   |
| `--motion-settle`  | 520ms | matter settling                                                       |

`--motion-ease-settle` is the curve: most of the speed in the first third and a long tail, mass
arriving and being absorbed. No overshoot — an overshoot is a bounce and a bounce is a toy.

The scale is set by a product that lives open on a second screen all day. `settle` is the only
step above half a second, and **nothing a person can fire twice per second is allowed to reach
it**.

Under `prefers-reduced-motion: reduce` everything collapses to one near-instant step. **Not zero**:
a cut with no transition is its own vestibular problem, while a short cross-fade is not, and it
keeps the interface legible for someone who set the flag for reading comfort rather than nausea.

### Do not

- Do not inline a duration. The reduced-motion override has to be able to reach it, and an inlined
  value makes that block dead code.
- Do not add a fifth duration to fit one component. Pick the nearest step; if none fits, the
  component is asking for the wrong thing.
- Do not animate a property that triggers layout. Opacity and transform, or a grid track.
- Do not animate a property that carries a datum. A height that IS a duration or a top that IS a
  start time shows a false number for as long as the animation runs — scale the other axis instead.

---

## Typography belongs to the theme (CRITICAL)

`--font-display`, `--font-sans` and `--font-mono` are theme tokens, and a component never names a
family. A brand is a voice before it is a colour, and a component that hard-codes a face is a
component that cannot be rebranded.

Every face carries a real fallback stack. A font that fails to load must degrade to something
with the same metrics, not to the browser default at a different width — a layout measured against
a display face and rendered in Times is a different layout.

- **The preset and the reset read `var(--font-sans)` / `var(--font-mono)`** and name no family —
  `src/test/themeTypography.spec.ts` fails if one comes back.
- **Body copy is sans; monospace is opt-in**, per element with `font-mono`, where a figure has to
  line up. A theme that wants mono prose says so in its own file — `battoni-dev` does, for `p`.

---

## When the brand fill cannot carry its own label

**`--color-primary-button` is the fill of a filled brand button, and nothing else.** It is optional:
the preset reads `var(--color-primary-button, var(--color-primary-500))`, so a theme that declares
nothing keeps the brand fill. Declare it when neither label clears 4.5:1 on `primary-500` — a
mid-tone accent (terracotta, orange, a mid blue) is the usual case. Moving `primary-500` would move
every icon, bar and highlight in the product; moving the button to `primary-600` collides with hover,
which IS `primary-600`, and costs the button the difference between resting and hovered. So the fill
sits between the two. **If you declare it, keep hover darker than it** — a fill that passes contrast
and no longer answers the cursor is not a fix.

## What a dark or mid brand changes in the preset

Three places used to assume the brand is pale, like `battoni-dev`'s lime, and measured under the
floor on largatudoevai's teal while `battoni-dev` passed:

- **A state label is `ink-950`, never `ink-900`.** The state palettes are the same in every theme
  and the ink is not: the theme's `ink-900` on `danger-500` was 4.90:1 in `battoni-dev` and 4.49:1
  in maresia, whose ink-900 is a dark teal. The darkest step reads on every state fill in any theme
  that keeps the ramp's shape (`battoni-dev` danger 5.58, maresia 5.13).
- **A tag filled with the brand takes `--color-primary-contrast`** (`celer-tag-bold-primary` on
  `primary-600`). A fixed ink read on the lime and measured 3.33:1 on the teal.
- **`--color-primary-text` is the brand as a WORD on a dark ground** — the focused float label and
  the outlined, text and link buttons, which Aura writes in `primary-500`. Optional: the preset reads
  `var(--color-primary-text, var(--color-primary-500))`, so a theme that declares nothing keeps
  `primary-500`. Declare it in the dark block when `primary-500` falls under 4.5:1 on the dark canvas
  or panel (maresia: 4.38 on the panel, and 4.00 on the canvas once its ramp moved) — usually one
  step lighter.

## Tags: the pair that renders is the one to measure

**The `surface` ramp does not invert between schemes** — `100` is pale and `800` is dark in both. A
dark-scheme rule that reaches for `surface.100` is asking for a pale ground. Aura's `secondary` Tag
reads `surface.600` on `surface.100` in light (3.13:1 in `battoni-dev`) and `surface.300` on
`surface.800` in dark (4.29:1) — both under the floor, on a chip read at 14px. The preset keeps
Aura's grounds and gives the text `--color-body`: 14.13:1 and 5.06:1.

The Tag with no `severity` is painted by `styles/components/tag.css` — but only while its `data-p`
is empty, and `rounded` fills it. A `rounded` default Tag (every list organism draws them) is painted
by the preset's `tag.primary` pair, which is the same one: `primary-50` under ink, 17.98:1.

_Retired:_ "so the preset's own `root` pair for it never renders". Nothing reads `tag.root.*` at all;
the pair that renders is `tag.primary.*`, and while the preset set only `root`, Aura's `primary-600`
on `primary-100` (1.83:1) is what a rounded default Tag showed in light.

**A pale tint takes ink, not the contrast token.** `primary-50`/`-100` do not flip with the scheme
and are light in every brand, so the word on them is a fixed dark (ink, or the ramp's own `950`, as
the navigation does). `--color-primary-contrast` answers for the 500 fill; a brand whose fill takes a
white label would put white on the tint. Never put a role token that flips (`text-muted`,
`text-body`) on a tint either: `MImageUpload` did, and read 3.34:1 in dark.

The `celer-tag-soft-primary` helper (`styles/components/tag.css`) follows the same rule: `primary-950`
on `primary-100`, 4.54:1 in `battoni-dev`, in both schemes (neither side flips). It wrote
`primary-700`, 1.83:1, on every list organism's `primary` tone. `e2e/theme/contrast.spec.ts` measures
every `celer-tag-*` helper and the default Tag on their own fills. `src/test/brandTintText.spec.ts` refuses a lighter step of the ramp on a pale
brand tint anywhere in a class string (it found an icon bubble in `OListFeed` and the idea "planned"
chip).

## Controls the preset colours outside the text tiers

Measured in `battoni-dev`, both schemes, by `e2e/theme/contrast-controls.spec.ts`:

| control                             | light                                                                              | dark                                                                                                             |
| ----------------------------------- | ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| SelectButton / ToggleButton option  | `muted` on `surface-100` 5.48 (Aura `surface-500`: 2.63); hover `surface-900` 8.11 | Aura's pair, unchanged                                                                                           |
| text button, `severity="secondary"` | `muted` — 7.15 · 6.49 · 5.48 (Aura `surface-500`: 3.43 · 3.11 · 2.63)              | `surface-400`, pressed on `surface-950` 8.00 / `surface-900` 5.58 (Aura: `surface-800` 3.77, `surface-700` 2.58) |
| default `rounded` Tag               | ink on `primary-50` 17.98 (Aura `primary-600` on `primary-100`: 1.83)              | Aura's pair, unchanged                                                                                           |

## The contrast floor is 4.5:1, and it is tested (CRITICAL)

Normal text, everywhere, including labels on filled buttons. 3:1 is for large text and graphics
and this project has very little of either.

**It is a test, not a habit**: `e2e/theme/contrast.spec.ts` resolves the token in a real browser
and computes the ratio against the fill it actually renders on. It has to be e2e — jsdom has no
cascade, so a unit test cannot resolve a custom property and cannot compute a ratio against a
colour it never resolved.

Where a fill genuinely cannot clear the floor, the answer is to change the FILL or to accept it
deliberately and write down why, in this file. It is never a per-component colour class: that is
how a system-wide rule dies, one exception at a time.

## `muted` reads, `subtle` does not (CRITICAL)

**`subtle` never carries a sentence. Text someone must read is `muted` or stronger.** That includes
the small print: a hint under a field, a timestamp, a list row's subtitle, a countdown, a
placeholder, a file name, an eyebrow above a section.

| tier     | for                                                                                     | floor, on canvas · panel · panel-muted |
| -------- | --------------------------------------------------------------------------------------- | -------------------------------------- |
| `muted`  | labels, secondary text, hints, placeholders, float labels (at rest and holding a value) | **4.5:1** (WCAG 1.4.3, normal text)    |
| `subtle` | icons, chevrons, a field's leading icon, a close glyph, decorative marks                | **3:1** (WCAG 1.4.11, non-text)        |

`subtle` has a floor too, because an icon that means something — the chevron that says a group
opens, the X that closes a toast — is a graphical object a person has to find. A purely decorative
mark (an overlay, a watermark) is exempt from 1.4.11 and may go fainter by opacity; the TOKEN still
clears 3:1 so that nothing meaningful can be painted with it by accident.

Measured in `battoni-dev`, canvas · panel · panel-muted:

| token    | scheme | before                             | after                              |
| -------- | ------ | ---------------------------------- | ---------------------------------- |
| `muted`  | light  | `surface-600` — 4.09 · 3.71 · 3.13 | `surface-800` — 7.15 · 6.49 · 5.48 |
| `subtle` | light  | `surface-400` — 1.90 · 1.72 · 1.45 | `surface-600` — 4.09 · 3.71 · 3.13 |
| `muted`  | dark   | `#888b8d` — 5.78 · 5.38 · 5.03     | unchanged                          |
| `subtle` | dark   | `#6f6f64` — 3.90 · 3.63 · 3.40     | unchanged                          |

`surface-700` was the closer step for `muted` and was rejected: 4.44 on the panel and 3.76 on
panel-muted, and a label sits on both. The preset follows the same split in light — PrimeVue's
`text.mutedColor`, a field's placeholder and its float label read `var(--color-muted)`, a field's
icon reads `var(--color-subtle)` (Aura painted them from `surface-500`/`-400`, 3.43:1 and 1.90:1 on
white). A float label that holds a value reads `muted` too (Aura: `surface-500`, 3.11:1 on the
panel), and one in a focused field reads `heading` — Aura's `primary-600` was 1.69:1 on white, and
the focus ring already carries the brand. `e2e/theme/contrast.spec.ts` measures all of them on all
three grounds in both schemes, and the mobile drawer as a fourth ground.

**Retired:** "hint text is `text-muted` to `text-subtle`". It let a sentence choose the faint tier,
and 57 sentences across the app did — at 1.72:1 on a panel, the one tier nobody could read.

---

## Adding a theme — derive it, or copy the template

There are two ways in, and the question that picks one is **does the brand change the role
tokens, or only the colours they point at?**

### Derive: `@import` a sibling and override the brand

For a brand that keeps the base theme's structure — the same role mapping, relief, atmosphere and
identity — and changes its colours, faces or radius. The file is a few kilobytes and every later
fix to the base reaches it.

```css
/* themes/terra.css */
@import './battoni-dev.css';

@theme {
  --color-primary-500: #b5722e; /* … the whole 50→950 ramp, and ink-* and surface-* */
  --color-primary-contrast: #ffffff; /* re-measured on YOUR primary-500 */
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
  --radius-md: 6px;
}

[data-theme='dark'] {
  --color-canvas: #16110a; /* … every dark role value, re-measured */
  --color-primary-contrast: #ffffff;
}

:root {
  --canvas-overlay: url('data:image/svg+xml,…%23b5722e…'); /* the base's tile is lime */
}
```

This works through `@ActiveTheme` as it is: the alias resolves to the derived file, and the
relative `@import` resolves from there. A second `@theme` block wins over the first, Tailwind emits
ONE value per token, and the base's light role tokens — `var(--color-surface-800)` and friends —
follow the new ramp without being touched. (Verified by building a throwaway derived theme.)

**A derived theme MAY override:**

- the brand ramps — `primary-*`, `ink-*`, `surface-*` — whole, all eleven steps;
- `--color-primary-contrast` in both schemes, and `--color-primary-button` if the fill needs it —
  **measured against the new fill**, never inherited on trust;
- fonts (`--font-*`, with the face added in `fonts.css`), radius (`--radius-*`), the `--shadow-*`
  steps and the four `--relief-*` tokens;
- the atmosphere: `--atmo-*`, and `--canvas-atmosphere` / `--canvas-overlay` in `:root`;
- the **dark role values**. `battoni-dev` writes them as literals tuned to its own neutrals, so a
  brand with a warm or cool cast re-declares them — same names, re-measured against the floors.

**A derived theme MUST NOT:**

- change the state palettes (they are the same in every theme — see "Three token tiers");
- add, rename or drop a role token, or re-point a LIGHT role token onto a different step — that is
  a different role mapping, and it belongs in a copied template where it is written down whole;
- restyle components or re-declare the base's identity selectors (`label`, `.p-tag`, `body`…) —
  override the tokens they read instead; if a selector reads no token, that is a fix for the base;
- `@import` a theme that itself imports another. One level: a chain of partial overrides is a theme
  nobody can read in one file;
- skip the measurement. What the base measured, it measured on ITS ramps. `e2e/theme/contrast.spec.ts`
  runs against the active theme, so run it with `VITE_ACTIVE_THEME` set to yours.

### Copy: start from `_template.css`

For a theme that changes the role mapping itself, or shares nothing with an existing one.

1. Copy `themes/_template.css` → `themes/<your-theme>.css`.
2. Fill the `primary` / `ink` / `surface` ramps with your brand scales. Copy the state
   palettes verbatim (change only if the brand mandates different state colors).
3. Map the role tokens, then **measure** `muted` (4.5:1) and `subtle` (3:1) on canvas, panel and
   panel-muted in both schemes — the template's suggested steps are a starting point, not a result.
4. Fonts differ? Update `--font-sans`/`--font-mono` and add the face in `fonts.css` — prefer an
   `@fontsource*` npm package over hand-placed WOFF2, so Vite resolves and fingerprints it and there
   is nothing to download or keep in sync. Define the four relief tokens even if they resolve to
   ambient shadows.

### Either way

1. Set `VITE_ACTIVE_THEME=<your-theme>` in `.env`. `colors.css` and `ACTIVE_THEME` in
   `src/constants/theme.constant.ts` both resolve from it — neither file is edited by hand.
2. Restart dev / clear `node_modules/.vite` (Vite caches the `@import` graph), then verify light + dark.

**No component files change** — they only use role tokens and state palettes.

---

## Known Gaps

- **No brand colour reads as text on the panel in `battoni-dev` light.** `primary-950` is the
  darkest step: 4.78:1 on the canvas, 4.34 on the panel, 3.66 on panel-muted. The preset uses it for
  the outlined, text and link buttons, the active tab and the highlighted option, all of which sit on
  the canvas or on the brand's own tints; `celer-button-link-primary` in `OWalletTransactions` sits
  on the panel and measures 4.34. Components that write words in `text-primary-*` (the idea reply
  label, the news reaction count, the sign-up terms link, the brand page) are in the same place.
  Open decision: a scheme-aware brand TEXT token (`--color-primary-text`, darker than the ramp in
  light) or ink for every word. Until it is taken, do not add a word in `primary-*`.
- _Closed 2026-09-17:_ the mobile drawer as a ground. It painted `--p-drawer-background`
  (`#252626` in dark: `muted` 4.43:1, `subtle` 2.99:1); `ONavbarDrawer` now paints its container
  `bg-canvas`, the ground the rail sits on, and the drawer case of `contrast.spec.ts` is green. The
  preset's `drawer.background` was left alone: other drawers (`MDefaultModal`) do not carry the text
  tiers on it.
