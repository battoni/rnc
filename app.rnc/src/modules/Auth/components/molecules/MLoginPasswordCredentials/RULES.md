---
version: 1.2.1
origin: pendulum
based-on: 1.2.1
---

# RULES — MLoginPasswordCredentials

> For AI agents. Last updated: 2026-09-14.

## Purpose

Multi-mode login form supporting phone (WhatsApp) or email identifier + password. Used for the "password credentials" auth flow variant. Posts to `auth/login` via `LoginPasswordCredentialsService`.

## Intentional Decisions

- **Identifier mode switches between phone (InputMask) and email (InputText)**: `identifierMode` ref controls which input renders. `identifierModeButtonKey` forces the SelectButton to re-render when deselecting the current mode (PrimeVue SelectButton doesn't support `allowEmpty: false` without a key reset trick).
- **Real `auth/login`**: the form normalises the identifier (e-mail as-is, phone to digits) and sends it as `username`. Success stores the session and follows `intendedPathFrom` / home. A 422 shows the API message (same sentence for missing account and wrong password) — it does **not** redirect to onboarding. A 429 shows `auth.login.tooManyAttempts`.
- **`selectButtonNoValidateFormControl`**: passed as `:formControl` to prevent PrimeVue Forms from validating the mode switcher as a form field.
- **`validateOnBlur: false, validateOnValueUpdate: false`**: validation only fires on submit, not on every keystroke or blur — intentional for a better UX on the identifier field which changes its validation rule based on mode.
- **`onIdentifierModeUpdate` clears the identifier field value**: when switching modes, the previous value is invalid for the new mode, so it's reset.
- **Phone digits: 10–11 digits** (Brazilian mobile numbers). Login by phone only works when that number is the account's `username` on the API.

## Edge Cases Handled

- **Mode deselect attempt**: `onIdentifierModeChange` increments `identifierModeButtonKey` to force-reset the SelectButton to the current value when the user tries to deselect (PrimeVue SelectButton would otherwise allow deselect despite `allowEmpty: false`).

## Do Not

- **Do not redirect failed logins to `auth.onboarding`** — that guessed “no account” and broke real users whose e-mail already existed.
- **Do not add `validateOnBlur: true`** — the identifier validation changes based on `identifierMode`, so early validation shows misleading errors.
