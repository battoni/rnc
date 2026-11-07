---
version: 1.3.1
origin: pendulum
based-on: 1.3.1
---

# RULES — MLoginForm

> For AI agents. Last updated: 2026-09-16.

## Purpose

Username + password login form for the standard credential-based auth flow.

## Intentional Decisions

- **`apiError` is set on 422 responses**: HTTP 422 surfaces a field-level message via `AFormError`; HTTP 429 shows `auth.login.tooManyAttempts`; any other error shows a generic toast. This distinction is intentional.
- **`messageKey` extracted from `data.message` or first `data.errors` value**: the backend may return errors in either format; the component normalizes both.
- **Yup errors are shown inline, under the field** (`Message`, `variant="simple"`), matching
  `MLoginPhone`, `MLoginPasswordCredentials` and `MLoginUsernamePassword`, which already did. The
  earlier "the invalid state is enough" rule is retired: a colour is not a sentence, and a screen
  reader announces "invalid" with nothing after it. Read the correction in the CHANGELOG before
  citing lifeshifter here — the parent was NOT silent; this molecule was simply the odd one out.
- **Uses `IftaLabel`** (not `FloatLabel`): the "inside the top" label variant from PrimeVue for username/password fields.
- **Forgot password link is a `RouterLink to="/esqueci-minha-senha"`**: hardcoded path intentionally (not a named route).
- **On success**: sets user + permissions in store, shows welcome toast, pushes to `home`.
- **Labels reach the real inputs**: `InputText` takes `id` (it IS the input); `Password` takes `inputId` and receives `autocomplete` through `:inputProps` — a bare `autocomplete` on it lands on the wrapper `<div>`.

## Known Gaps

- **No call site.** `LoginUsernamePasswordFlowView` renders `MLoginUsernamePassword`, not this; the
  phone and password flows render their own molecules too. Nothing outside the barrel imports this
  file — no view, no e2e, and its only spec is its own. It is either a fourth variant somebody
  planned or dead code, and that is the owner's call, not a cleanup to do in passing.

## Prop & Emit Contract

No props or emits — self-contained login form.

## Do Not

- **Do not use `AFormError` for a field-level rule** — it carries what the API refused (429, an unexpected failure), and a Yup rule has a field to sit under.
