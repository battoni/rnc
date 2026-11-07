---
version: 1.0.1
origin: pendulum
based-on: 1.0.1
---

# RULES — ClickGuard

> For AI agents. Last updated: 2026-09-17.

## Purpose

One intent, one action. A second click on the same button, landing before the first one's work is
done, is thrown away instead of running the handler again — which on a "create" is two rows in
somebody's database, and on a vote or a delete is worse.

Installed once, from `src/plugins/index.ts`. **Nothing opts in.** A button written next month is
covered without its author knowing this exists; that is the point, and it is the reason the guard
lives on `document` rather than in a directive or a mixin somebody has to remember.

## How it works

A single **capture-phase `click` listener on `document`**. Capture, and on `document`, because it
is the only seat reached before the button's own handler no matter who rendered the button.

1. The click's target is resolved to its nearest `<button>` with `closest`.
2. A button (or an ancestor) matching `OPT_OUT_SELECTOR` is left alone.
3. A button already holding a lock has its click killed — `preventDefault()` **and**
   `stopImmediatePropagation()`, so the handler, every ancestor handler, and a form's implicit
   submit all hear nothing.
4. Otherwise the button takes a lock for `GUARD_WINDOW_MS` (350ms) and is stamped
   `data-click-guard="locked"`.
5. If the click _raised_ the app's busy signal (`useAppBusy`) in the same turn, the lock is held
   until that signal drops — so a slow request keeps its button quiet for as long as it runs, not
   for a flat 350ms.

## Intentional Decisions

- **Per element, never global.** The lock lives in a `WeakMap` keyed by the button, so two
  different buttons never guard each other and an unmounted button takes its lock with it. The bug
  is "one intent, two records", not "the person is clicking too much".
- **A lock is never permanent.** Every path that takes one also arms a timer that releases it:
  `GUARD_WINDOW_MS` for the plain case, `MAX_HOLD_MS` (8s) for a held one. A handler that throws,
  hangs, or never settles leaves a button that waits, not a button that is dead. **Any change here
  must keep that true** — a guard that can strand the UI is worse than the bug it fixes.
- **The busy hold only fires on a rise.** If the app was already busy when the click landed there
  is no transition to attribute to it, so the plain window is all the guard claims. That is also
  the safety valve: a leaked request counter cannot freeze every button in the app.
- **350ms.** Above pointer jitter and a fat-fingered double-tap, below any deliberate re-click.
- **Promises are not visible from here.** A listener cannot see what a handler returned, so work
  that does not go through the `ApiProvider` (and therefore never raises `useAppBusy`) is covered
  only by the 350ms window. The other half of the contract — a handler that hands its promise back
  disables the button until it settles — needs a component that calls the handler itself
  (`AGuardedButton` in lifeshifter). **It was not ported to rnc**; see Known Gaps.

## The escape hatch

`data-allow-rapid-click`, on the button or on any ancestor (read with `closest`, so a wrapper
exempts everything inside it). For a control whose whole job is to be pressed again immediately.

```vue
<Button data-allow-rapid-click icon="pi pi-chevron-right" @click="onNextMonthClick" />
```

In use today: the reaction buttons in `MNewsReactions`. `OPT_OUT_SELECTOR` additionally exempts PrimeVue sections we cannot annotate at the
call site — the InputNumber spinner, the DatePicker arrows, and anything inside a Paginator.

## What it does NOT cover

Say this plainly rather than implying more:

- **Only `<button>` elements.** A clickable `<div>`, an `<a>`, a card with `@click` on it: not
  guarded. Adding `[role="button"]` here would be a deliberate widening, not a bug fix.
- **`mousedown`/`pointerdown` handlers.** The guard is on `click`. (This is why the InputNumber
  spinner is safe by construction — it repeats on `mousedown`.)
- **Anything that is not a click at all** — a keyboard-driven `@keydown.enter`, a form submitted
  by pressing Return in a field, a programmatic `submit()`.
- **The unit-test harness.** `mountWithPlugins` / `renderWithPlugins` do NOT install the guard, on
  purpose: a spec that clicks the same button twice in a row is testing the component, not the
  person, and installing it would break dozens of them. Specs that mean to exercise the guard
  install it themselves (`startClickGuard` / `stopClickGuard`), as `clickGuard.spec.ts` does.

## Known Gaps

- **No promise-aware button.** lifeshifter's `AGuardedButton` (a pass-through `Button` wrapper that
  disables itself while the promise its handler returned is pending, 8s ceiling, never touching
  `loading`) was not ported. Here, a handler whose slow work does not go through the
  `ApiProvider` — a `setTimeout`, a third-party SDK, a `FileReader` — gets only the 350ms window.
  Porting it means an atom plus a resolver that sends `<Button>` to it, which touches the
  auto-import setup (`components.d.ts`, which is not versioned); it is an open decision, not an
  oversight. If it arrives, it must honour `data-allow-rapid-click` with the same `closest` read, so
  the two never disagree about who is exempt.

## Do Not

- **Do not make the guard opt-in.** A solution every future author has to remember has already
  failed.
- **Do not add a release path that has no timer behind it.** See "a lock is never permanent".
- **Do not reach for `data-allow-rapid-click` to fix a button that feels slow.** It is for controls
  that are genuinely meant to repeat — a stepper, an arrow. A save button that needs it has a
  different problem.
- **Do not read `data-click-guard` as state.** It is a mark for a spec and for devtools; the lock
  itself is the `WeakMap`.
