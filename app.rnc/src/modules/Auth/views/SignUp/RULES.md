---
version: 1.0.1
origin: pendulum
based-on: 1.0.1
---

# RULES — SignUpView

> For AI agents. Last updated: 2026-05-22.

## Purpose

Thin wrapper view that composes `AuthFlowLayout` + `MSignUpUser`. No logic lives here.

## Intentional Decisions

- **No `#topActions` slot**: sign-up has no back button — users arrive here from the phone lookup flow which already knows they don't have an account.
- **No `#bottomActions` slot**: sign-up has no secondary CTA.

## Do Not

- **Do not add routing or API logic** — everything is in `MSignUpUser`.
