---
version: 1.1.2
origin: pendulum
based-on: 1.1.2
---

# RULES — MPersonalizationVisualCard

> For AI agents. Last updated: 2026-09-17.

## Purpose

Card on the Personalization page for this device's preferences: dark mode, language, full-screen layout, and a pinned menu button.

## Intentional Decisions

- **Stores are the source of truth**: the `colorScheme` and `ui` stores and `useLocale` handle persistence (`localStorage`, per device); this molecule only binds controls to them.
- **The card is `rounded-lg`**, the card step of the radius scale — `xl` is for a modal.
- **Dark mode switch sets explicit LIGHT/DARK**: toggling does not preserve `SYSTEM` preference.
- **Row icons inside labels**: moon, expand, and lightbulb for quick visual scanning.
- **`useId()` base + suffixes** pair labels with ToggleSwitch `inputId`.

## Prop Contract

No props — reads and writes global UI stores directly.

## Do Not

- **Do not add navigation or save actions here** — preferences persist automatically via stores.
- **Do not add API calls** — preferences persist in localStorage only.
- **Do not add a preference that needs the server** or has to follow the person to another device — that is a user setting, the User module's business (see the module `RULES.md`).
