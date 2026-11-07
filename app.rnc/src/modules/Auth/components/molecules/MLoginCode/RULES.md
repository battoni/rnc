---
version: 1.3.2
origin: pendulum
based-on: 1.3.2
---

# RULES — MLoginCode

> For AI agents. Last updated: 2026-09-17.

## Purpose

OTP code entry form used after a phone/email OTP is sent. Includes a 60-second resend cooldown timer.

## Intentional Decisions

- **The resend cooldown is `useCountdown`** (`LOGIN_CODE_RESEND_COOLDOWN_SECONDS`), started in `onBeforeMount`. It replaced a hand-rolled `setInterval` that decremented once per tick: a phone suspends timers while the person reads the code in another app, and the countdown came back still locked. The composable reads the wall clock and clears itself on unmount.
- **`phone` is read from `route.query.phone`**: the previous view must pass the phone number as a query param. If missing, a toast error is shown and no resend request is made.
- **`isResendTimerVisible`** is separate from the cooldown state: the timer display only appears after the first resend attempt while cooldown is still active.
- **OTP validation**: 4 digits, integers only, exact length 4. Validated via Yup on form submit.
- **On success navigates to `auth.onboarding`**: this is the next step in the registration/login flow after code verification. When `route.query.email` is present it is forwarded so signup can prefill the address.

## Prop & Emit Contract

No props or emits — self-contained step in the auth flow.

## Edge Cases Handled

- **Resend during active cooldown**: shows a toast explaining the wait; does not make an API call.
- **Missing phone on resend**: shows a toast and exits early.
- **Component unmounted during countdown**: `useCountdown` stops with the component's scope.

- **The OTP value lives in a local `code` ref, mirrored into the Form** (primevue#7025). PrimeVue
  Forms can hold only the LAST digit of an `InputOtp`, so the form state is not trusted for the
  value: `v-model="code"` is the source of truth, `@update:modelValue` pushes the whole string into
  the field with the slot's `onChange`, and submit reads `code`.

## Do Not

- **Do not count the cooldown down per tick** — use `useCountdown`.
- **Do not declare `.length()` before `.required()`** in the resolver — an empty string fails both,
  and the first failing test is the message the person reads.

A line here said "do not add a v-model for the OTP value"; it predates the primevue#7025 fix above, which is exactly a `v-model` on a local ref, and is retired for contradicting it.
