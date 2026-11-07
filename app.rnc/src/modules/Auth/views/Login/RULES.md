---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — Auth Login Views

> For AI agents. Last updated: 2026-09-16.

## Purpose

Thin wrapper views that compose `AuthFlowLayout` + one login form component each. There is one view per login flow variant.

## View Map

| File                            | Form Component                    | Flow                     |
| ------------------------------- | --------------------------------- | ------------------------ |
| `LoginPhoneFlowView`            | `MLoginPhone` → `MLoginPhoneCode` | Phone → code, real API   |
| `LoginCodeView`                 | `MLoginCode`                      | E-mail code entry (mock) |
| `LoginUsernameFlowView`         | `MLoginEmail`                     | Email → OTP              |
| `LoginPasswordFlowView`         | `MLoginPasswordCredentials`       | Phone/email + password   |
| `LoginUsernamePasswordFlowView` | `MLoginUsernamePassword`          | Username + password      |

## Intentional Decisions

- **`LoginPhoneFlowView` is the exception: it owns a step machine.** One `codeRequest` ref — `null`
  is the phone step, a response is the code step. The code step is keyed by phone, so a resend
  keeps its countdown and a new number remounts it. The session is stored in this view with
  `setUserAndPermissions(user, user.permissions ?? [])` and the person goes to
  `intendedPathFrom(route.query)` or home — the same as the password forms. An expired code sends
  the person back to the phone step with a warning above it; a code burned by five wrong guesses
  sends them back with the number still filled in and an info toast saying why — without it the
  person kept typing into a code that could never work.
- **Each other view wraps one form component**: views own only the title (i18n key) and navigation links (`#topActions`/`#bottomActions` slots). No logic lives in the view.
- **"Need help" links**: most views link to `auth.support`; the password-credentials flow links to `auth.resetPassword`. Do not normalize these — they are distinct recovery paths.
- **Back button in `LoginUsernameFlowView`**: links to `/entrar` (literal path). This is intentional — email flow is a secondary path reachable from the main login.
- **`LoginCodeView` back button** links to `auth.login` (named route): this is the phone OTP flow's canonical home.

## Known Gaps

- **The e-mail code flow has no API.** `LoginUsernameFlowView` → `MLoginEmail` →
  `LoginCodeView` → `MLoginCode` still runs on `VerifyLoginCodeService`, a mock, and `MLoginEmail`
  asks for the code through the REGISTRATION resend endpoint. The phone flow stopped passing
  through `LoginCodeView` when it got a real API (2026-09-16); the e-mail flow needs its own
  endpoint before it can do the same.

## Do Not

- **Do not add routing logic or API calls in the single-form views** — all logic lives in the form components.
- **Do not merge views** — each represents a distinct product auth flow that may be individually enabled or disabled.
