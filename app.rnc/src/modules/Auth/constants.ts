/** Matches `LoginCodeService::CODE_LENGTH` in the API. */
const LOGIN_CODE_LENGTH = 6;

/** How long the resend button waits after a code went out. */
const LOGIN_CODE_RESEND_COOLDOWN_SECONDS = 60;

/**
 * How long "send the code again" waits on the sign-up verification screen. The API throttles
 * `auth/email/code` (`throttle:registration`); the wait is what keeps a person from spending
 * that allowance on impatient clicks.
 */
const SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS = 60;

export { LOGIN_CODE_LENGTH, LOGIN_CODE_RESEND_COOLDOWN_SECONDS, SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS };
