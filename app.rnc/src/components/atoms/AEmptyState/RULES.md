---
version: 1.1.2
origin: pendulum
based-on: 1.1.2
---

# RULES — AEmptyState

> For AI agents.

## Purpose

The branded empty-state placeholder for lists/grids with no data: a bracketed tile
(an icon), a `>_` code-symbol glyph, optional title/description, and an action slot.

## Intentional Decisions

- **An icon, always — never the brand mark.** `icon` defaults to `pi pi-inbox`. It used to fall
  back to `<ABrandMark>`, whose accessible name is a person's, so an icon-less empty state
  announced it to a screen reader in every product built from this bootstrap. There is also a
  structural reason it cannot come back: this unit is `MERGE` and travels, `ABrandMark` is
  `LOCAL` and does not, so the fallback pointed a shared component at the brand.
- **`>_` glyph**: the brand's terminal cue; overridable via `glyph`.
- **`.brand-corners`**: corner-bracket overlay (theme-driven) frames the tile.
- **All text optional**: `title`/`description` only render when provided; copy is passed pre-translated by the caller (`$t(...)`).

## Prop & Emit Contract

- `title?`, `description?`: pre-translated strings.
- `glyph?`: terminal glyph (default `>_`).
- `icon?`: PrimeIcon class (default `pi pi-inbox`).
- Slot `action`: optional CTA (e.g. an "Add" button).

## Do Not

- Do not reintroduce a brand fallback. A shared unit must not reach into `LOCAL` brand assets.
- Do not pass i18n keys; pass already-translated strings.
