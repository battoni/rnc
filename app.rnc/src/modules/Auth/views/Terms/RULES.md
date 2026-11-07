---
version: 1.0.2
origin: pendulum
based-on: 1.0.2
---

# RULES — TermsView

> For AI agents. Last updated: 2026-09-15.

## Purpose

Static terms and conditions page using `TheCenteredLayout` directly (not `AuthFlowLayout`). Accessible from the sign-up form's terms link.

## Intentional Decisions

- **Uses `TheCenteredLayout` directly**: terms page has a wider content area than the auth card. Do not wrap in `AuthFlowLayout`.
- **Back button is absolutely positioned** (`absolute top-4 left-4`): overlays the content without affecting the centered layout.
- **Links back to `auth.entrar`** (named route): the canonical auth entry point.
- **Content is i18n-driven**: no hardcoded legal text — all content comes from `auth.flow.terms.*` keys.
- **Phone gutter on the content wrapper** (`w-full px-4`): `TheCenteredLayout` is `max-w-[360px]` with no horizontal padding, so at 360px the column is edge-to-edge. Padding lives on this view's wrapper, not on the shared layout (ForgotPassword / NotFound keep their own chrome). Paragraphs use `break-all` so a long token cannot force horizontal scroll.

## Do Not

- **Do not add `px-*` to `TheCenteredLayout` for this fix** — that layout is shared; fix the Terms content shell here.
