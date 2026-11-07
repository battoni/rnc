---
title: The brand-mark contract for app.rnc — the props, motion and accessibility any logo component must honour
outline: deep
---

::: info Generated page
This page is generated from `.claude/rules/celer-09-brand-mark-contract.md` — **edit the source rule, not this page.** Activates for: `app.rnc/**/*.vue`, `app.rnc/**/*.ts`.
:::

# The brand mark — a contract that travels, for a component that does not

`app.rnc/src/components/atoms/ALogo/` is `LOCAL` in the sync manifest. It has to be: the mark is
the one thing a product cannot inherit, and a bootstrap that overwrote it would replace every
project's identity on every sync.

That is correct and it has a cost. **Everything learned about how a mark should BEHAVE is learned
inside a file that never leaves.** lifeshifter's `ALogo/RULES.md` grew from 28 lines to 148 —
the second most thickened contract in the family — and none of it can reach another project. So
the behaviour lives here instead, in a file that is `OVERWRITE` and does travel, and each
project's `ALogo/RULES.md` describes only how ITS mark implements this.

Read this before adding a prop to a logo component. If a rule here does not fit the mark you
have, the rule is wrong and belongs in this file's next version — not silently ignored in one
project.

---

## The prop surface

A mark answers to the app; it never asks the app anything.

| prop | shape | means |
| --- | --- | --- |
| `variant` | `'full' \| 'min'` | `full` is mark + wordmark, `min` is the mark alone. No third value: a logo with four spellings is four logos. |
| `tone` | `'brand' \| 'inherit'` | `brand` paints from the brand token; `inherit` lets the parent's colour cascade, which is what makes a mark usable inside a filled button or a dark band. |
| `pulse` | `number \| string \| undefined` | A value, never an event — see below. |
| `loop` | `boolean` | The app is busy. |
| `entrance` | `boolean` | Play the assembly once, on mount. |

A mark that is a raster image supports `variant` and stops there, and that is a complete
implementation of this contract. The motion props are for a mark drawn as inline SVG; do not add
them to an `<img>` and animate the element, which moves the picture instead of the mark.

## `pulse` is a value, not an event

The call site binds something that CHANGES when the app goes somewhere — the navigation organisms
bind `route.fullPath` — and the atom watches it. Nothing about routing enters the atom. An atom
that imported `useRoute` would be an atom that only works in an app with routes.

**A bound pulse announces arrival on mount.** The route transition swaps views `out-in`, so the
navigation remounts on every route change and a watcher alone would never fire. One move on mount
when `pulse !== undefined` is the whole mechanism behind "the logo plays on every route change".
Unbound, the mark stays at rest.

## `loop` is the app's working signal, not a spinner

Bind it to whatever means "the person is waiting" — router pending state plus in-flight requests.
The loop chains full gestures with a breath between them: it must read as breathing, never as
thrashing, and it stops by letting the last move settle rather than by removing a class mid-gesture.

It is not a substitute for a spinner on a control. A button that is saving says so itself; the
mark says the application as a whole is busy.

## One statement at a time

Hover and pulse are refused while the loop or the entrance is playing, and the loop waits for the
entrance. A mark playing two things at once is a mark that looks broken, and the rule is cheaper
to keep than the arbitration is to debug.

## Reduced motion is absence, not shortening

Under `prefers-reduced-motion: reduce` the script refuses to arm any motion AND a media query
removes the animation outright — both, because either alone leaves a path in. Every part of the
mark then sits at its resting position, which must be a **complete, correct mark on its own**.

That last sentence is the real rule. Nothing may be gated behind a class that has to arrive: if
the mark is only right after an animation has run, then the mark is wrong for everyone who
switched motion off, and for the first frame everyone else sees.

## Accessibility

- In `full`, the SVG is `aria-hidden`: the adjacent wordmark already says the name, and two
  readings of one thing is worse than none.
- In `min`, it carries `role="img"` and an `aria-label` — **the product's name, not a person's**.
  An empty state falling back to a personal monogram announced one for months; see
  `AEmptyState/RULES.md`.
- The motion is decorative and announces nothing.

## Do Not

- Do not give an inline-SVG mark fixed ids. Anything the SVG references by id — a `clipPath`, a
  gradient, a mask — is scoped with `useId()`. The navigation renders two marks at once (rail and
  drawer), and with a repeated id the second one clips against the first (lifeshifter).
- Do not hard-code a fill. The shape takes `currentColor` and the colour comes from a token, which
  is what lets `tone="inherit"` work at all.
- Do not add `dark:` variants — the token flips.
- Do not give the atom a size. It fills what the parent constrains.
- Do not import a mark's SVG with `?url`. Under `?url` Vite inlines anything below
  `assetsInlineLimit` (4 kB) as a data URI, and a non-ASCII character in the SVG — an accented
  `aria-label`, say — makes a data URI some engines refuse to decode: the mark disappears with no
  error. `?no-inline` always emits a real file.
- Do not put the product's own motion vocabulary in this file. How many cells a mark has, what
  order they arrive in and what that order argues are the PRODUCT's, and they belong in the
  project's own `ALogo/RULES.md`. What belongs here is only what any mark, in any project, has to
  get right.
