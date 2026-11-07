---
version: 1.0.3
origin: pendulum
based-on: 1.0.3
---

# RULES — APageHeaderAction

> For AI agents. Last updated: 2026-09-17.

## Purpose

Standard page-header CTA button. Renders icon + label from `md` up; below `md`, icon only with `aria-label`.

## Intentional Decisions

- **Breakpoint is `md` (768px)**: matches `ThePageHeader` hamburger visibility and mobile bottom-nav layout.
- **Mobile sizing**: below `md`, the button is a `size-11` (44px) square with centered icon (`p-0`) — the touch floor (celer-06); a default-size button is not grown by `touch-target.css`. From `md` up, sizing follows the default app.rnc button classes (`w-fit`). _Retired:_ `size-10`, 40px, under the floor.
- **The press transition uses `motion-calm`**, never a literal `duration-*`, so `prefers-reduced-motion` shortens it (`styles/tailwind.css`).
- **`label` is a resolved string**: pass `$t('key')` from the parent — this atom does not resolve i18n keys.
- **`variant` maps to existing app.rnc button classes**: do not pass custom `class` from views; use `variant` and `isQuiet`.
- **Attrs fall through to `Button`**: use for `data-testid`, `v-tooltip`, `@click`, and `v-if` on the component.

## Prop Contract

- `icon`: PrimeIcon class string (required).
- `label`: accessible and visible label text (required).
- `variant?: 'soft-primary' | 'primary'` — default `soft-primary`.
- `isQuiet?: boolean` — `shadow-none` soft-primary style for secondary header actions. Default `false`.

## Known Gaps

- **Icon-only below `md` against celer-06's "shrink, never hide the label".** The label is hidden
  below `md` and kept as the `aria-label`. Whether a header CTA is an accepted exception to that
  rule is an open question (review 2026-09-17); until it is decided, this atom keeps the icon.

## Do Not

- **Do not use for in-list row actions** — only for `#primaryAction` (or a header CTA) in
  `ThePageHeader`.
- **Do not omit `aria-label` behavior** — mobile icon-only mode depends on `label` for accessibility.
