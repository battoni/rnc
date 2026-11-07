---
version: 1.0.0
origin: rnc
---

# RULES — Brands Module

> For AI agents. rnc-3 F2, 07/11/2026.

The operator's console of Franco's sub-brands (rnc-3 R5, D3): `/operador/marcas` (+ `/operator/brands`),
`brands.manage` — the operator's alone. Express and Prime are `BrandSeeder` rows of the one Franco
firm (D1); no brand is created or deleted here (Firm `RULES.md`, W30).

## Intentional Decisions

- **The main brand's row is read-only** (`MainBrandResource`): Franco's look is the theme's
  (`franco.css`), not a row to edit. It shows the name, the two colours and the main brand's leads.
- **"Ver como" opens `/?marca=<slug>&previa=1` in a new tab** (`window.open`, `noopener`), where the
  public site wears the preview banner (A6). It is disabled and reads "Inativa" for an inactive brand
  (AM12): the site would only fall back to Franco there.
- **The switch is reversible, so no confirm** — as the partner registry. A brand turned off stays in
  the console and in the board's filter; its leads keep their chip.
- **The form measures as it is typed**: white on the primary with `contrastRatio` (the API's own rule,
  4,5:1 — D4), and a strip painted from `brandPalette`, the same ramp the site will use. The API's
  refusal comes back by field and is named by its code (`BRAND_ERROR_KEYS`), never by its sentence.
- **Colour maths is the Site module's** (`@SiteModule`: `brandPalette`, `contrastRatio`) — one ramp for
  the console's preview and the site's theme.

## Known Gaps

- **No new brand from the console** — a third sub-brand is a seeder row (Firm Known Gap, W30).
