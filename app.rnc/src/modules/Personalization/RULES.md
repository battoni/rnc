---
version: 1.0.0
origin: pendulum
based-on: 1.0.0
---

# RULES — Personalization Module

> For AI agents. Last updated: 2026-09-17.

## Purpose

Global interface preferences for this device: colour scheme, language, full-width layout and a pinned
menu button. One page (`PersonalizationView`) and one card
(`MPersonalizationVisualCard`).

## Intentional Decisions

- **This module owns no data of its own.** Every preference it edits belongs to a store or a
  composable elsewhere — `useColorSchemeStore`, `useUiStore`, `useLocale` — and each persists its
  own value to `localStorage`. The module is a page over other units' state.
- **Preferences are per device, not per account.** They are read before a session exists, which is
  why they live in `localStorage` and not on the user record. The card's description says so ("this
  browser only"), and must keep saying so.
- **No route permission.** Anyone signed in may change how the app looks for them.

## Do Not

- **Do not add a preference that needs the server.** The moment it needs an endpoint — or has to
  follow the person between devices — it stops being a personalization and becomes a user setting,
  which is the User module's business (from lifeshifter 469603a).
- **Do not describe these preferences as saved "to your profile"** — they are not.
