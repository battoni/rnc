---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — PersonalizationView

> For AI agents. Last updated: 2026-09-17.

## Purpose

Dedicated page for global UI preferences (dark mode, language, full width, pinned menu button).

## Intentional Decisions

- **Template-only view** — no script block; composition lives in `MPersonalizationVisualCard`.
- **Bilingual routes**: `/personalization` (`personalization-en`) and `/personalizacao` (`personalization-pt-BR`).
- **`ThePageHeader` title uses i18n key** `personalization.title`.
- **The wrapper is a `<div>`** — `TheLayout` already renders the page's `<main>`; do not nest a second one.

## Do Not

- **Do not add inline toggles elsewhere** — this view is the single UX surface for these preferences.
