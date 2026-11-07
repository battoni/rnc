---
version: 1.2.1
origin: pendulum
based-on: 1.2.1
---

# RULES — Auth Layouts

> For AI agents. Last updated: 2026-09-17.

## Purpose

`AuthFlowLayout` is the shared wrapper for auth flow pages: brand aside (lg+), centered card with
logo, titled header, and optional top/bottom action slots. The authorship signature sits under the
main column.

## Intentional Decisions

- **Split layout, not `TheCenteredLayout`.** On large screens a primary-branded aside carries the
  brand pattern and copy; the main column centers the card. Mobile hides the aside and keeps the
  card + signature. (Older RULES described a centered-layout shell with an absolute logo — that
  no longer matches this file after the 1.1.0 redesign.)
- **Logo sits above the card** (`ALogo` variant `min`, centered). It is not absolute top-left.
- **`title` renders in the card header** before a `Divider`. If omitted, the header area has padding
  but no text — intentional for headless flows.
- **`#topActions` / `#bottomActions`** render above / below the card. Neither has a fallback.
- **Card is `max-w-[400px]`** inside the main column.
- **Compact vertical chrome so phone viewports fit without scroll (CRITICAL).** Main uses `py-4`,
  logo `mb-4`, signature `pb-4`. Larger padding (`py-10` / `mb-8` / `pb-8`, and even `py-6`) pushed
  login password (card + “need help” bottom action) past 360×800. Do not reintroduce generous
  padding without checking that height.

- **Everything on the aside takes `text-primary-contrast`** (with its `/70`, `/80`, `/90`
  opacities), never a raw `text-ink-*`. The fill is `bg-primary-500`, and which ink reads on it is
  the active theme's `--color-primary-contrast` — dark on this lime, white on a dark brand.
  `AuthFlowLayout.spec.ts` fails on an `ink` class inside the aside.

## The hero copy is the project's (CRITICAL)

Every visible string in the brand aside is an i18n key — `auth.brand.tag`, `auth.brand.headline`,
`auth.brand.tagline`, `auth.brand.domain`. The core locales carry neutral bootstrap lines; a project
says its own in `src/locales/local/en.json` and `pt-BR.json`, which are deep-merged over the core
(`libraries/i18n`) and never touched by a sync.

This file is MERGE in the manifest, and both projects that grew from it rewrote the hero in the
template — a conflict on every sync for a change that was only ever copy. **Do not put a literal in
the aside**, and do not edit this template to change what the hero says. `headline` is rendered with
`whitespace-pre-line`: a `\n` in the value is where the line breaks.

## Prop & Emit Contract

- `title`: plain translated string (call site resolves i18n before passing). Renders as an `<h1>`.
  Pass empty string or omit to suppress it.
- `#topActions`: slot for a back link or secondary navigation above the card.
- `#bottomActions`: slot for a "need help" link or secondary CTA below the card.
- `#default`: the form component (e.g. `MLoginPhone`, `MSignUpUser`).

## Do Not

- **Do not add auth-specific logic** (routing, validation) to this layout — it is a pure
  presentational container.
- **Do not pass a pre-resolved i18n key** — the call site is expected to pass a translated string
  (uses `t('...')` before passing, not `$t` inside this component).
