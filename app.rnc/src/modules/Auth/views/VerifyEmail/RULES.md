---
version: 1.3.1
origin: pendulum
based-on: 1.3.1
---

# RULES — VerifyEmailView

> For AI agents. Last updated: 2026-09-17.

## Purpose

Where someone types the six digits that prove they read the address they signed up with.
Reached from `MSignUpUser` after a registration that is waiting on verification, carrying
the address in `?email=`.

## Intentional Decisions

- **The address is on screen.** Without it the page is six anonymous boxes and the reader
  has no way to know which inbox to open — or that they typed the address wrong. The
  subtitle uses `break-all` so a long address does not force horizontal scroll at phone
  widths (~360px).
- **The OTP field is capped at six characters** (`:maxlength="6"`), matching the code length.
- **A 422 raises nothing.** A wrong code is the most ordinary outcome of typing six digits,
  and the field already says so. A toast for it teaches people to ignore toasts. Anything
  that is not a 422 is the API being unreachable, and that does get one.
- **Re-sending always says the same thing**, and says it conditionally: "if there is a
  registration for that address". The API refuses to confirm whether an address is
  registered here, and a screen that said "sent!" would give away exactly that.
- **Resend waits a minute, by the wall clock** (`useCountdown`, `SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS`).
  It starts before the first frame, because arriving here means a code has just gone out, and again
  after each resend. The seconds left sit beside the button. The person is in their mail app while
  it runs, and a phone suspends the timers of a page it is not showing — a per-tick countdown came
  back still locked.
- **A 429 on resend is a warning in words** (`errors.tooManyAttempts`), not "something went wrong".
- **The person is signed in by the API**, not by this screen. Verification returns the user
  with a session already established; this only puts it in the store and leaves.
- **Except an account parked for approval.** With `registration.new_user_status` = inactive the
  API returns the account without a session. The screen does not store it — the guard would
  bounce it to login in silence — and says, as an info toast, that an administrator approves it.

## Do Not

- **Do not make the code field a `Password`** — it is not a secret, it is a number the
  person is copying from another window, and hiding it only causes typos.
- **Do not report a wrong code with a toast.**
- **Do not word the re-send confirmation as a fact** ("code sent to ana@…"). That is an
  account-enumeration leak with a friendly voice.
