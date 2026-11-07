---
version: 1.1.1
origin: pendulum
based-on: 1.1.1
---

# RULES — AFormError

> For AI agents. Last updated: 2026-09-17.

## Purpose

Renders a form/API error string, with special handling that detects "unexpected" errors (backend stack traces or codes) and presents them in a copyable `Message` box.

## Intentional Decisions

- **Two distinct rendering paths**: if the error matches the `errors.unexpected` i18n pattern (detected by splitting on `{error}`), it shows an intro sentence + a copyable code block; otherwise it shows a plain `Message`. Do not collapse these into one.
- **Copy appends the current route name**: when the user copies the error, the route is appended to aid debugging. This is intentional — do not remove it.
- **`isCopied` auto-resets after 2 seconds**: the icon toggles to `pi-check` then reverts; the `setTimeout` call in `onCopyClipboardSuccess` drives this. There is no persistent copy state.
- **The copy control is a real `<button>`**: `type="button"`, `touch-target`, and an `aria-label` that follows the state (`errors.copy` → `errors.copied`). A clickable `<i>` takes no focus and has no name — do not go back to it.
- **Renders nothing when `error` is falsy**: the root `v-if="error"` guards the entire component. An empty string, null, or undefined all produce no output.

## Prop & Emit Contract

- `error`: the raw error string from the API or form validation. Pass `null` or `undefined` to render nothing. Pass the full unexpected error message (including the intro text) — the component parses it internally.

## Edge Cases Handled

- **Missing `{error}` placeholder in i18n**: if `parts[0]` is empty after splitting, `isUnexpectedError` returns `false` and the plain path is used.
- **Empty `errorParts.error` after stripping intro**: `copyError` guards on `!errorParts.value?.error` and returns early.
- **Clipboard API failure**: `.catch` shows a toast instead of swallowing the error.

## Do Not

- **Do not pre-parse the error before passing**: pass the raw string; the component does the split internally using the live `errors.unexpected` i18n key.
- **Do not add a `#icon` slot override** on the unexpected-error `Message` — the copy icon is rendered there intentionally.

## Dependencies & Context

- Reads `errors.unexpected` from the i18n catalogue — that key must contain a `{error}` placeholder for detection to work.
- Uses `useRoute().name` only for clipboard copy context; it does not affect rendering.
