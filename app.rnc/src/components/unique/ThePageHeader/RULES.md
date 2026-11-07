---
version: 1.1.3
origin: pendulum
based-on: 1.1.3
---

# RULES — ThePageHeader

> For AI agents. Last updated: 2026-09-16.

## Three slots, and which is which

| Slot                      | Where it renders                                                                |
| ------------------------- | ------------------------------------------------------------------------------- |
| `leading` / `titleSuffix` | beside the title, at every width                                                |
| `primaryAction`           | on the title row, at every width — the one thing this screen is for             |
| `actions`                 | inline on a wide screen; behind `⋮` on a phone, but ONLY with `collapseActions` |

## The actions slot renders ONCE, never twice (CRITICAL)

Inline or inside the overflow panel, chosen with `v-if` on `useViewport` — never two copies with
one hidden by `md:hidden`. Two copies mean two elements answering the same `data-testid` and two
components claiming the same template ref.

That single rendering is also what makes popovers work. An earlier version of this component
rejected an overflow menu outright, because the actions anchor `ConfirmPopup`s and a popover
anchored to a button inside a closed menu has nothing to attach to. Putting the REAL buttons in the
panel solves it: the anchor exists exactly when it is needed, because the panel is open at the
moment the person clicks. **The panel deliberately does not close on a click inside it** — closing
would pull the anchor out from under the confirmation it just opened.

## The collapse decision is NOT a `computed` (CRITICAL)

`isCollapsed()` is a function called from the template, so it is answered on every render. It must
stay one.

`slots` is a plain object: Vue writes into it when the parent re-renders and nothing tracks that
write. A `computed` over `slots.actions` therefore freezes at whatever was true on the first paint,
and it broke in both directions. A page whose actions **arrive later** never got its `⋮` —
Novidades hangs a read filter here that exists only once the list has loaded, so the filter stayed
inline and pushed a 360px phone out to 421px. A page whose actions **leave** — any screen
withdraw theirs while a dialog is open — kept the `⋮`, which then opened an empty panel over the
dialog.

`isMobile` is still reactive and still read inside the function, so the render effect tracks it and
a rotation still repaints.

## `collapseActions` is opt-in, and that is deliberate

Off by default. A screen whose actions include date navigation is made
WORSE by hiding a high-frequency control behind a menu. Those screens keep their actions inline
until each has been looked at and given a real primary action.

Opted in so far: Ideias, Novidades, Usuários, Perfis.

## `basis-64` on the title block is load-bearing

With `flex-1` alone the title shrinks to whatever the actions leave behind, which on a phone turned
"Novidades › Uma entrada longa" into "N…". The preferred basis says the title would like 16rem; when
the actions cannot also fit, the row wraps them onto their own line. Two honest rows beat one
unreadable one.

## Never a forced column

The original stacked into a column below `md` without exception, which on a screen with a
four-letter title and one button pushed that button onto its own row with the width sitting empty
beside it. A wrapping row handles both cases: it stays one line when one line fits.

## Testing

Every spec that stubs this component must render `primaryAction` too — `src/test/render.ts` and the
per-view stubs both do. A stub that only renders `actions` silently drops the screen's main button
and the assertion fails somewhere else entirely.

## Row 1 never wraps on a phone (CRITICAL)

The root is `flex-nowrap md:flex-wrap`. Below `md` the header is one row — menu, title, and the
actions the screen is for — and nothing may change that. The actions are `shrink-0`; the title is
`whitespace-nowrap` and is **never truncated**.

Two things follow, and both have been got wrong here before:

- **Do not add `truncate` to the title.** It was tried; a page named "Personaliza…" is worse than
  the problem it solved. If a new title genuinely cannot fit, shorten the title itself or give that
  screen a short mobile variant.
- **Do not solve a fitting problem by shaving pixels.** That fix has failed three times — a longer
  title, a font change, an unmeasured layout mode. The room comes from the layout's padding
  (`TheLayout`) and the phone type size. `e2e/navigation/navigation.spec.ts` ("the page header
  keeps its row on one line") asserts one row at 360, and the same file asserts no side scroll.

## Known Gaps

- **Only 360 is measured, in one layout mode.** The spec this file used to cite
  (`e2e/tracks/mobile.spec.ts`, 320/360/390 in both modes) belongs to the project this contract came
  from and was never ported. 320 and 390, and the full-width mode, are unchecked here.
- **At 390 `/showcase` clips its own title to ">\_ V"** (pt-BR; "Sho" in English): two labelled
  actions inline leave the title 39px. By this file's rule that is the screen's to fix, not a reason
  to truncate here — see the Known Gap in `ShowcaseView/RULES.md` (VIS-19).
