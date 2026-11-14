---
version: 3.0.0
origin: pendulum
based-on: 1.2.0
---

# RULES — ALogo

> For AI agents. Last updated: 2026-11-14 (rnc-4 F2c2).

## Purpose

The firm's mark (rnc-1): a TEXT logo. `full` writes the firm's logo text, `min` is its initial's
tile. No image file. The build carries no name (rnc-4 R15): the caller passes it — `MFirmLogo` the host
firm's, `ThePublicLayout` a sub-brand's, `MNavbarBrand` the rail's.

**The behaviour contract lives in `.claude/rules/celer-09-brand-mark-contract.md`**, not here. This
file is `LOCAL` — the sync never touches it — and records only how this project's mark implements it.

## Intentional Decisions

- **Text, not a raster or an SVG.** The firm has no vector mark handed over yet, and the owner chose
  a text logo for the pilot. Text cannot fail to load, scales with the type and takes the brand token.
- **`variant`, `tone` and `name`.** `name` is the firm's logo text, or the public site's sub-brand
  (rnc-3 R3), written in the same mark. With no name the mark draws nothing.
  _Retired 14/11/2026 (rnc-4 F2c2):_ "the back office never passes it" (default "Franco
  Contabilidade") — the default named one tenant on every firm's screens. The motion props (`pulse`, `loop`, `entrance`) are for an inline-SVG
  mark; a wordmark does not animate.
- **`tone="brand"` paints `text-brand-word`** — navy on paper, the theme's `--color-primary-text`
  (the cyan) on a dark ground; `tone="inherit"` leaves the parent's colour, for a filled band such as
  the sign-in screen's navy aside. _Retired 07/11/2026:_ "`tone="brand"` paints `text-primary-500`" —
  that step is navy on the dark scheme's near-black header too (1.6:1), and the public site's header
  and footer lost the mark there.
- **Sized by the parent's font size.** The tile is `1.75em` square, so `text-xl` on the parent makes a
  bigger mark; the atom sets no size of its own.
- **`min` is `role="img"` with the product's name** as its `aria-label`; `full` needs none — the
  words are the name.

## Prop & Emit Contract

- `variant`: `'full'` (default) | `'min'`.
- `tone`: `'brand'` (default) | `'inherit'`.
- `name`: the firm's (or a sub-brand's) logo text; no default — none draws nothing.

## Do Not

- **Do not write "Willy" or "APW"** here or anywhere in the product (rnc-1 R3).
- **Do not hard-code a colour** — the token flips with the theme.
