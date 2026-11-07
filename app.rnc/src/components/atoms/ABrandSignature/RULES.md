---
version: 1.0.2
origin: pendulum
based-on: 1.0.2
---

# RULES — ABrandSignature

> For AI agents.

## Purpose

The "engineered by battoni.dev" lockup: the monogram (`ABrandMark`) next to a two-line
monospaced caption. Used as a quiet authorship signature in footers and auth chrome.

## Intentional Decisions

- **Monospaced voice**: caption uses `font-mono`, uppercase tracking — the brand's technical tone.
- **Mark colour flips per scheme**: `text-ink-900` light, `dark:text-primary-500` dark.
- **Text-driven**: both lines are props with brand defaults; no slot needed.

## Prop & Emit Contract

- `label`: small upper caption (default `engineered by`).
- `wordmark`: the brand wordmark line (default `battoni.dev`).

## Do Not

- Do not import `ABrandMark` — it is auto-imported.
- Do not pre-translate the caption; this is brand chrome, not i18n content.
