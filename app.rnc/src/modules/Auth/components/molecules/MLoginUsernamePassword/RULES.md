---
version: 1.4.1
origin: pendulum
based-on: 1.4.1
---

# RULES — MLoginUsernamePassword

> For AI agents. Last updated: 2026-09-14.

## Purpose

Username + password login form with icon-prefixed inputs. Used in `LoginUsernamePasswordFlowView` as an alternate to `MLoginForm`.

## Intentional Decisions

- **`relative isolate` on field wrappers**: `isolate` creates a new stacking context so the absolute icon `z-10` stacks correctly inside the field without interfering with adjacent elements.
- **`validateOnBlur: false, validateOnValueUpdate: false`**: same as `MLoginPasswordCredentials` — validation fires only on submit.
- **Icon color changes on invalid state**: `$form.field?.invalid ? 'text-danger-500' : 'text-surface-300'` — part of the field validation UX, consistent with other auth forms.
- **Separate from `MLoginForm`**: this is a distinct component for the username/password login flow (`auth.flow.login.welcome` heading). Keep both — they serve different product configurations.
- **422 / 429 error handling** is identical to `MLoginForm`: `messageKey` extracted from `data.message` or `data.errors` first value; HTTP 429 shows `auth.login.tooManyAttempts`.

## Prop & Emit Contract

No props or emits — self-contained login form.

## Do Not

- **Do not merge with `MLoginForm`** — they have different field layouts, labels, and branding headings.
