---
version: 1.0.0
origin: rnc
---

# RULES — MFirmLogo

> For AI agents. rnc-4 F2c2, 14/11/2026.

## Purpose

The HOST firm's mark (rnc-4 R15): `ALogo` with the firm's `logo_text`, for the screens that show the
mark on their own — the sign-in aside and card (`AuthFlowLayout`), forgot password, terms, not found,
firm not found and the repricing letter.

## Intentional Decisions

- **A molecule that reads a composable, not a prop** — like `MNavbarBrand`: the firm is the app's
  (`useFirmBrand`, the tab's last answer until the API replies), and threading it through six screens
  that do not otherwise know it was the alternative. It only READS the composable; the boot read is
  `useBrandTheme`'s, so this is not the "business logic in a molecule" `components/RULES.md` refuses.
- **`ALogo` keeps the contract of `celer-09`**: the mark never asks the app anything. This molecule is
  the one that asks, and it passes the answer down as `name`.
- **Draws nothing until a firm is known**, and nothing on "firm not found" (there is no firm), so no
  name the build carries ever shows.
- **`variant`, `tone` and classes fall through** to `ALogo` (single root, `inheritAttrs` default).

## Prop & Emit Contract

None of its own; `variant` and `tone` reach `ALogo` as fallthrough attributes.

## Do Not

- **Do not use it where a sub-brand is shown** — `ThePublicLayout` passes the brand's `logo_text`
  itself, and the rail's block is `MNavbarBrand`.
