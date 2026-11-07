---
version: 1.1.1
origin: pendulum
based-on: 1.1.1
---

# RULES — MLoginEmail

> For AI agents. Last updated: 2026-05-22.

## Purpose

Email entry form for the email-OTP login flow. Sends an OTP to the provided email and navigates to the code entry view.

## Intentional Decisions

- **Navigates to `auth.entrarCodigo` with `{ email }` query param**: the code view uses the email query param for display and potential resend. Do not drop this query param.
- **Email icon color changes on invalid state**: `$form.email?.invalid ? 'text-danger-500' : 'text-surface-300'` — the icon is part of the validation UX.
- **No API error surface** (no `AFormError`): this form only has client-side validation; API errors show as toasts.

## Prop & Emit Contract

No props or emits — self-contained step in the auth flow.

## Do Not

- **Do not redirect to a named route other than `auth.entrarCodigo`** after success — this is the email OTP flow, not the phone OTP flow.
