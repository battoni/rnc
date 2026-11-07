---
version: 2.0.0
origin: pendulum
based-on: 2.0.0
---

# RULES — MLoginPhone

> For AI agents. Last updated: 2026-09-16.

## Purpose

The phone step of the phone-code sign-in. Asks the API for a code and hands the answer to the view.

## Intentional Decisions

- **It asks for a code, never whether the phone has an account.** `auth/phone/code` answers the
  same either way, so this step has nothing to branch on and moves on after any success. The old
  `LookupPhoneService` mock was removed: a lookup endpoint is a directory of who uses the product.
- **It emits, it does not navigate.** `onSuccess(response, typedPhone)` — the view owns the step
  machine and the session. `typedPhone` is the masked value, so "change number" refills the field
  exactly as it was typed (the response carries the normalised `55…` digits instead).
- **The masked value goes to the API as typed.** The API normalises it in `prepareForValidation()`.
- **Validated as a Brazilian number with its area code** (`isValidBrazilPhone`) before a request
  is spent; the mask is Brazilian too. A product outside Brazil replaces both.
- **Refusals are toasts with a reason**: 429 → `errors.tooManyAttempts`, `delivery_failed` →
  `auth.flow.login.codeDeliveryFailed`, anything else the generic sentence.
- **Social login buttons are non-functional stubs** (Google, Facebook). The e-mail button goes to
  the e-mail flow, which has no API yet — see `views/Login/RULES.md`.

## Prop & Emit Contract

| Prop           | Type     | Default |
| -------------- | -------- | ------- |
| `initialPhone` | `string` | `''`    |

| Emit        | Payload                                              |
| ----------- | ---------------------------------------------------- |
| `onSuccess` | `(response: LoginCodeRequested, typedPhone: string)` |

## Do Not

- **Do not reintroduce a lookup** to decide between sign-in and sign-up — see above.
- **Do not store the session or route from here.**
