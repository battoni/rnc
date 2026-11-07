---
version: 1.5.0
origin: pendulum
based-on: 1.5.0
---

# RULES — MSignUpUser

> For AI agents. Last updated: 2026-09-16.

## Purpose

Full user registration form with personal details, CPF, birth date, password, and terms acceptance.

## Intentional Decisions

- **Email prefills from `route.query.email`** when arriving from login/OTP with an address already typed.
- **HTTP 422 maps `errors` per field** into `serverErrors` (email/cpf “already taken” use dedicated i18n keys). The Laravel summary message is not shown as the field error.
- **A server error clears the moment its field is edited** (`onFieldInput`), not on the next submit. It describes the value that was SENT; leaving it up tells someone who just typed a brand-new address that it is already taken, red border and all, until they submit again — which is where most people stop.
- **Laravel's English never reaches a field.** `RegisterRequest` declares no `messages()`, so a 422 arrives as prose, not a key — that is the normal case here. `messageForField` tries `te()` first (other auth endpoints do answer with keys), then the named shapes, then a neutral translated sentence. It must NOT fall back to `translateError`, which wraps the English in the `errors.unexpected` template and turns one short correction into an exception report.
- **CPF uses `InputMask` with `mask="999.999.999-99"`**: raw mask string including dots and dash. Backend receives the formatted string.
- **Phone is optional, masked as a Brazilian mobile** (`BRAZIL_PHONE_MASK` from `@Constants`, with a WhatsApp icon), and checked for an area code plus 8 or 9 digits (`isValidBrazilPhone`). The API prefixes the country code; the field says the number may receive messages.
- **Validation runs on submit only** (`:validateOnBlur` and `:validateOnValueUpdate` off). A long form that turns fields red while the person is still filling it in reads as a scolding. After a refused submit a field re-validates on the next submit, not per keystroke — the trade taken on purpose.
- **Birth date uses `InputMask` with `mask="99/99/9999"`** (`DD/MM/YYYY`) and is sent as `YYYY-MM-DD` through `fromDayMonthYear` + `toLocalDateString` (`@Helpers`), never `toISOString()`. A day that does not exist is sent as typed, so the API refuses it on the field. "Backend receives the formatted string" was written before `toIsoDate` existed and is retired: the API reads a masked date as month 17.
- **`password` has `feedback: true`** (shows strength meter); `passwordConfirmation` has `feedback: false` — intentional asymmetry.
- **`passwordConfirmation` uses `.test('match', ...)` with `this.parent.password`**: Yup's `oneOf` shorthand doesn't work well when both fields update; custom test is more reliable.
- **Terms checkbox links to `/termos`** with `target="_blank"`: opens in a new tab. Do not change to an in-app route.
- **On success navigates to `home`** directly after registration.

## Prop & Emit Contract

No props or emits — self-contained registration form.

## Do Not

- **Do not strip CPF formatting** before sending to the API — backend handles the formatted string. (The birth date is converted; see above.)
- **Do not serialize a date with `toISOString()`.**
- **Do not add `autocomplete` to CPF or birth date fields** — browser autofill for these is not supported by the current form.
