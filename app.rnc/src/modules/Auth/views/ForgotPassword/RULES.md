---
version: 1.1.3
origin: pendulum
based-on: 1.1.2
---

# RULES — Auth ForgotPassword Views

> For AI agents. Last updated: 2026-09-14.

## Purpose

Multi-step password recovery flow across four views: support contact page, recover access form, reset code + new password form.

## View Map

| File                        | Purpose                                                                                                         |
| --------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `ForgotPassword.view.vue`   | Shows support admin names for manual account recovery (uses `TheCenteredLayout` directly, not `AuthFlowLayout`) |
| `SupportView.vue`           | Shows `MSupportForgotPassword` inside `AuthFlowLayout` — contact support page for forgot-password               |
| `RecoverAccessView.vue`     | Phone OR email form to request a reset code                                                                     |
| `ResetPasswordCodeView.vue` | New password + OTP code form to confirm the reset                                                               |

## Intentional Decisions

- **`ForgotPassword.view.vue` uses `TheCenteredLayout` directly** (not `AuthFlowLayout`): it has a different visual structure with back button in absolute position and a wider content area.
- **`RecoverAccessView` has custom cross-field validation**: both phone and email are optional individually, but at least one must be provided. The Yup `at-least-one-recover` cross-field test handles this at the schema root.
- **`isSubmitAttempted` ref in `RecoverAccessView`**: tracks whether the form has been submitted at least once. The "at least one field required" error only shows post-submit, not while the user is filling in fields.
- **`emailValue` is a separate `ref` in `RecoverAccessView`**: PrimeVue Forms doesn't propagate the value of plain `v-model` inputs to `$form`; the email field uses both a PrimeVue `FormField` name binding AND a local ref for the "at least one" validation check.
- **`ResetPasswordCodeView` reads `route.query.token`** on `onBeforeMount`: if no token is present, it redirects to `auth.recuperarSenha`. Never navigate directly to this view without the token.
- **`onConfirmRecoverAccess` in `RecoverAccessView`** uses `'mock-reset-token'` as a fallback: this is temporary scaffolding until the token is reliably returned by the API.

- **The OTP value lives in a local `code` ref, mirrored into the Form** (primevue#7025). PrimeVue
  Forms can hold only the LAST digit of an `InputOtp`, so the form state is not trusted for the
  value: `v-model="code"` is the source of truth, `@update:modelValue` pushes the whole string into
  the field with the slot's `onChange`, and submit reads `code`.

## Do Not

- **Do not remove `isSubmitAttempted`** — it prevents premature "at least one field required" errors.
- **Do not navigate directly to `ResetPasswordCodeView`** without a valid `?token=` query param.
