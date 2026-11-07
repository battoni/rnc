---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — MLoginPhoneCode

> For AI agents. Last updated: 2026-09-16.

## Purpose

The code step of the phone-code sign-in: six boxes, the number the code went to, a resend with a
countdown and a way back to change the number. Rendered by `LoginPhoneFlowView`.

## Intentional Decisions

- **The code lives in a local `code` ref, not in a PrimeVue Form.** Forms can keep only the last
  digit of an `InputOtp` (primevue#7025); there is nothing else to validate here.
- **`autocomplete="one-time-code"` reaches the real inputs through `pt`** (`pcInputText.root`), so
  iOS and Android offer the code from the message. On `InputOtp` itself the attribute would land
  on the wrapper and do nothing.
- **The sixth digit submits.** The button is there for a paste that did not trigger the watcher
  and for people who expect one; it stays disabled until the code is complete.
- **Refusals switch on `response.data.code`, never on the sentence** (`LOGIN_CODE_ERROR`):
  `invalid_code` clears the boxes and keeps the message under them (a keystroke clears it);
  `code_expired` emits `onCodeExpired` and `code_locked` (the fifth wrong guess burned it) emits
  `onCodeLocked`, so the view can send the person back for a new code; a 422
  with an `errors` bag (a deactivated account) shows the translated key. None of these is a toast.
- **The boxes are disabled while checking**, and focus returns to the first one only after they
  are enabled again — focusing a disabled input does nothing.
- **The number is shown as a person writes it** (`formatBrazilPhone`) and does not wrap.
- **`devCode` is shown only when the API exposed one** (`LOGIN_CODE_EXPOSE`, never in production).
- **The countdown starts before the first frame** (`onBeforeMount`), because mounting this step
  means a code was just sent. The view keys the step by phone: a resend keeps the countdown's owner,
  a new number remounts it.
- **The countdown is `useCountdown`, by the wall clock.** A per-tick decrement kept "resend" locked
  long past 60s on a phone that suspended the timers while the person read the code elsewhere.

- **Constants and the enum are imported relatively** (`../../../constants`, `../../../enums`).
  The `resendCooldownSeconds` default is read while the `@AuthModule` barrel is still being
  evaluated; through the barrel it threw "Cannot access … before initialization" and every screen
  of the app went blank. The unit spec mocks the barrel and could not see it — only a browser did.

## Prop & Emit Contract

| Prop                    | Type                                          | Default                              |
| ----------------------- | --------------------------------------------- | ------------------------------------ |
| `phone`                 | `string` — the normalised number from the API | required                             |
| `devCode`               | `string`                                      | `undefined`                          |
| `resendCooldownSeconds` | `number`                                      | `LOGIN_CODE_RESEND_COOLDOWN_SECONDS` |

| Emit            | Payload                                         |
| --------------- | ----------------------------------------------- |
| `onSuccess`     | `User` — the API has already opened the session |
| `onCodeExpired` | —                                               |
| `onCodeLocked`  | — the code was burned by too many wrong guesses |
| `onResent`      | `LoginCodeRequested`                            |
| `onChangePhone` | —                                               |

## Do Not

- **Do not store the session here** — the view does, with the same call the password forms make.
- **Do not word a wrong code as a toast.** It is the commonest outcome of typing six digits.
