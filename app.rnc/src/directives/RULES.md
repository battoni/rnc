---
version: 1.0.1
origin: rnc
---

# RULES — src/directives/

> For AI agents. rnc-1, 07/11/2026.

Entrance and hover motion for the public site and the diagnostic, as Vue directives imported from
`@Directives` (`import { vReveal } from '@Directives'` — a `v*` constant in `<script setup>` is
the directive, `celer-03` section 1). The styles they switch on are in
`styles/components/motion.css`; the durations are the tokens in `tailwind.css` (theme RULES, Motion).

| Directive                       | What it does                                                                                                  |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `v-reveal` / `v-reveal.stagger` | The element rises when it first scrolls into view; `.stagger` numbers its children (`--reveal-index`)         |
| `v-count-up`                    | A figure that opens with a number ("35 anos", "500+") rolls from zero when first seen; the text never changes |
| `v-spotlight`                   | A brand light under the mouse (`--spot-x/--spot-y`); nothing for a finger                                     |
| `v-tilt` (`v-tilt="4"`)         | Leans toward the mouse, up to N° (default 5), settles back on leave; off under reduced motion                 |

## Intentional Decisions

- **Nothing is ever hidden by markup alone.** `v-reveal` sets `data-reveal="pending"` (the hidden
  state) only after confirming an `IntersectionObserver` exists and the person has not asked for
  less motion. A section can never be left at opacity 0 because a script failed or an observer
  never fired; without JavaScript the page is the plain page. `reveal.directive.spec.ts` guards it.
- **A mount-time entrance is CSS only** (`.motion-enter`, `.motion-word`, `.motion-wipe-*` in
  `motion.css`): a `backwards`-filled animation always runs to its end. Use it for what is on screen
  when the page opens — the hero, a diagnostic step that just arrived. `v-reveal` is for what is
  below the fold.
- **One `IntersectionObserver` for every element** (`inView.ts`): a landing has thirty revealed
  blocks, and thirty observers cost more than the motion.
- **`v-count-up` never writes `textContent`.** The in-between value is painted over the real text
  (`data-show` in a pseudo-element); the DOM, a screen reader and a test read the final figure from
  the first frame. Only a figure that OPENS with its number rolls — "ISO 9001" is a name, and a name
  that passes through 5.640 reads as another standard.
- **Reduced motion is read at the moment it matters** (`prefersReducedMotionHelper`), never cached at
  module load. `v-spotlight` stays on under it: a hover tint that follows a pointer the person is
  already moving is not motion.
- **The pointer directives ignore a touch** (`pointerType !== 'mouse'`): "toward the pointer" has
  no meaning under a finger.

- **A directive is imported where it is used, and the spec reads every `.vue` to check it**
  (`directiveImports.spec.ts`). None is registered globally, so a template with `v-count-up` and no
  `vCountUp` import only warns and the effect silently never runs (RadarView, rnc-2). A new
  directive exported from `index.ts` is covered with no edit.

## Do Not

- Do not put a duration in a directive. `v-count-up` reads `--motion-settle` from the stylesheet
  so the reduced-motion collapse reaches it.
- Do not animate the axis that carries a datum (theme RULES, Motion). A bar's height that IS a
  rate wipes in sideways (`motion-wipe-x`); a bar's width that IS a share thickens (`motion-wipe-y`).
