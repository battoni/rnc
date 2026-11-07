---
version: 1.2.3
origin: pendulum
based-on: 1.2.3
---

# RULES — MMainDialog

> For AI agents. Last updated: 2026-09-17.

## Purpose

Standard add/edit action sheet rendered as a bottom-anchored PrimeVue `Dialog`. Provides a title, Cancel/Submit footer buttons, and scoped mask inside `<main>` via `appendTo="self"`.

## Intentional Decisions

- **The content scrolls with `.scrollbar-brand`** (`pt:content`, `styles/components/scrollbar.css`). The component used to carry its own copy of those rules; there is one definition now.
- **The default footer's two buttons are `gap-3` (12px) apart** — the touch floor between controls (celer-06).
- **The close button is `pointer-coarse:size-11`** (through `pt:pcCloseButton:root:class`): 44px under a finger, PrimeVue's 40px under a mouse.
- **`appendTo="self"`**: the dialog mask is scoped to the parent `<main>` element (not `body`), so the navbar stays visible on desktop. The closest ancestor must establish a containing block — `TheLayout`'s `<main>` does this with `transform-[translateZ(0)]`. Rendering outside `TheLayout` will break mask scoping.
- **Default `modal: false`, `closeOnEscape: false`, `dismissableMask: false`**: intended for explicit add/edit flows — a dialog is closed by its X or by its own buttons, and nothing else, because it holds a half-filled form and a stray tap on the page behind should not throw the work away. Do not flip these defaults without a product reason.
  **This line used to claim "tapping outside still closes", and it was never true.** PrimeVue gates that on `dismissableMask && modal`, so with `modal: false` the prop did nothing; the default now matches the behaviour.
- **`position: 'bottom'` default**: the bottom-slide animation only applies when position is `bottom`. The `<style>` block's keyframes target `.p-dialog-bottom .main-dialog-root`.
- **`<style>` is global (not scoped)**: when PrimeVue teleports elements, Vue scoped styles don't match. Keep this block global. Do not add `scoped`.
- **`main-dialog-root` class is required**: the slide animation selectors rely on it. Do not remove it from `dialogClass`.
- **`main-dialog-content-wrapper` class on the content div**: `MMainDialog`'s CSS uses `main:has(.main-dialog-root) .main-scroll { overflow: hidden }` to lock scroll when open. This depends on `main-dialog-root` being present.
- **`cancelButtonProps` / `submitButtonProps` use factory defaults** (`() => ({ ... })`): prevents shared-object mutation across instances.
- **Translation happens inside the component**: `title`, `cancelButtonProps.label`, and `submitButtonProps.label` are i18n keys resolved with `$t` internally. Pass keys, not pre-translated strings.
- **Default footer Cancel + Submit buttons are sticky**: `sticky bottom-0 z-10` keeps buttons visible when content overflows. Do not remove this wrapper.

## The mask paints the gutters

The sheet does **not** fill its mask horizontally, and the mask used to paint nothing, so the two
gutters were a hole straight through to the page.

- The sheet keeps a 32px side margin from `md` up — that is the page's own rhythm, and it is what
  makes the sheet read as resting **on** a desk rather than as a panel welded to the frame.
- The page's horizontal padding is **not** always 32px. `TheLayout` steps it in both layouts —
  `px-6 md:px-8` narrow, `px-3 sm:px-4 md:px-6 lg:px-8` full-width — so between 768 and 1023 the
  content sits 24px in while the sheet sits 32px in. The cards stuck out on each side and slid
  down the gutters as a rounded edge every time a dialog opened.
- **Measured at 768**: mask 746 wide, sheet 682 — 32px of bare glass left and right, plus the
  10px top seam. Identical at 1024 and 1440.

The mask now takes `--color-canvas`, which fixes it at every width instead of at the one width
where the sheet's margin and the page's padding happen to agree. **Canvas, not a scrim**: this
mask is scoped to `<main>`, so it ends exactly where the page header begins, and a dark frame
around the sheet would end in a hard line under the page title. Canvas makes the gutters and the
10px seam read as the page's own ground — which is what they are.

**From `md` only.** Below it the sheet is the whole screen, so the mask has no edge left to show;
painting it there would just blank the page behind the rising sheet.

**The fill animates, and it is the `background-color` that animates — not the mask's opacity.**
Opacity would take the sheet with it. Painted outright, the fill would blank the page half a
second before the sheet arrived and snap it back half a second after the sheet left. Enter and
leave both run on `--motion-settle` / `--motion-ease-settle`, the same pair the sheet uses, so
reduced motion reaches them. The leave rule carries `forwards` to hold the drained state for the
frames between the animation ending and PrimeVue removing the element.

**Paint, not capture.** PrimeVue writes `pointer-events` inline on the mask from the `modal` prop,
so the gutters still pass clicks to the page beneath. That is unchanged behaviour and the pixels
involved are the page's own padding; closing it would mean flipping `modal`, which buys a focus
trap, an escape handler and a scroll lock nobody asked for.

## Whole screen on a phone

Below `md` the sheet has no margin, no rounding, and fills `100dvh`. `appendTo` stays `self`: the
mask is still scoped to `<main>`, which on a phone starts below the page header (measured at 390:
mask from 60px down). The sheet is anchored to the mask's bottom and is a full dynamic viewport
tall, so it rises past the mask's top and covers the header too (sheet from 0 to 800). No
`appendTo` switch is needed for that.

_Retired wording:_ "and `appendTo` is `body` below `md`". The child project (lifeshifter) switches
`appendTo` by viewport; this bootstrap never did, and the line described code that is not here.
Whether to port the switch is an open question (Known Gaps).

**The media query is LAST in the stylesheet on purpose.** The `.p-dialog-bottom` rules above set
their own margin and height at the same specificity, so only source order decides between them —
placed earlier, the phone rule is silently overridden and the sheet comes out 10px short.

The close control stays at `right-2.5 top-3.5` at every width. _Retired wording:_ "moves to
`right-4` below `md`" — the code never did. The scrollbar belongs to `.p-dialog-content`, which
starts below the header, so the close button does not sit on it (measured at 390: button 14–54px,
content from 92px).

## The footer inherits the sheet's bottom corners

`.p-dialog-footer` is opaque, spans the full width and ends exactly on the sheet's bottom edge. Its
background is `--color-canvas` — the same tone as the mask and the page — so it does not read as a
grey seam under the body in dark scheme. `--color-panel` was wrong here: the sheet is not a floating
panel overlay; the mask is already canvas, and the footer has to disappear into it.

Left alone its corners are square while the sheet's are `26px`, so it fills each bottom corner out past
the arc of the sheet's 1px ring — and the ring reads as a light line cutting across a solid corner.
Only the dark scheme shows it; in light the ring is the same value as what sits behind it.

The sheet cannot clip the overflow away. `overflow: hidden` on `.p-dialog` would also clip the
absolutely-positioned close button and any focus ring that leaves the box. The footer takes the
radii instead.

**`inherit`, never a number.** The phone media query at the end of the stylesheet sets
`border-radius: 0` on the sheet; `inherit` follows it and the footer squares off with everything
else, where a hard-coded `26px` would round the two corners that rule exists to flatten.

**The header is deliberately left alone.** Its background is transparent, so it never paints over
the top corners — the sheet's own rounded fill does. A matching pair of top radii would be a rule
guarding nothing, and the next person could not tell whether removing it was safe.

## Prop & Emit Contract

- `v-model:visible`: required. Controls dialog open/close. The component never closes itself — emits drive close.
- `title`: i18n key for the `<h2>` header. Ignored when `#header` slot is provided.
- `isHeadless`: hides the header entirely (title + close button). Use instead of conditionally providing `#header`.
- `isFooterless`: hides the footer entirely (buttons + slot). Use when no footer is needed.
- `contentClass`: applied to the content wrapper div. Defaults to `flex min-h-0 flex-col gap-4`.
- `headerClasses` / `footerClasses`: forwarded to PrimeVue via `pt:header` / `pt:footer`.
- `cancelButtonProps` / `submitButtonProps`: `{ severity, variant, label }` where `label` is an i18n key.
- `@onClose`: fired when default Cancel is clicked. Consumer sets `visible = false` and resets state.
- `@onSubmit`: fired when default Submit is clicked. Consumer validates, persists, then closes.

## Edge Cases Handled

- **`#footer` slot present**: default Cancel/Submit are not rendered — they are mutually exclusive with the `#footer` slot.
- **`isHeadless` with `closable: true`**: `isHeadless` takes precedence — the close button is not rendered.

## Known Gaps

- **`appendTo="body"` below `md` was not ported** (lifeshifter 64a9154). The sheet already covers the
  header without it (see "Whole screen on a phone"); what the switch would add is a mask on the
  viewport instead of `<main>`. Open question, not a defect.

## Do Not

- **Do not add `scoped`** to the `<style>` block — it breaks styles for teleported elements.
- **Do not remove `main-dialog-root`** from `dialogClass` — animation and scroll-lock CSS depends on it.
- **Do not pass pre-translated strings** to `title` or button `label` — the component calls `$t` internally.
- **Do not override `appendTo`** — mask scoping depends on `appendTo="self"` + `TheLayout`'s containing block.
- **Do not paint the footer with `--color-panel`** — it must match the canvas mask/sheet or the dark scheme shows a grey seam under the buttons.

## Dependencies & Context

- **`TheLayout`**: requires `transform-[translateZ(0)]` on `<main>` for `appendTo="self"` to scope the mask correctly. See `TheLayout/RULES.md`.
- **`main-scroll` CSS contract**: `main:has(.main-dialog-root) .main-scroll { overflow: hidden }` locks scroll during dialog. Defined in this component's global `<style>`.
