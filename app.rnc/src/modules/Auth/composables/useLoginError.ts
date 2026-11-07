import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { LoginApiError } from '../interfaces';
import { HTTP_STATUS } from '@Enums';

const TOAST_LIFE_MS = 10000;

/**
 * How a login form answers a refused sign-in — one behaviour, three doors.
 *
 * `MLoginForm`, `MLoginUsernamePassword` and `MLoginPasswordCredentials` are different ways of
 * naming the same person to the same `auth/login`, so they had the same 35-line `.catch` copied
 * three times. That is not a tidiness problem: the 429 branch was added to all three by hand and
 * a fourth entry point would have shipped without it, showing Laravel's raw "Too Many Attempts."
 * on a Portuguese screen. Adding a case here reaches every form at once.
 *
 * `apiError` is the sentence UNDER the field, the toast is the one that flies past. A refusal the
 * form can restate keeps both; anything else keeps only the toast, because "something went wrong"
 * pinned beneath a password box tells the reader nothing they can act on.
 */
function useLoginError() {
  const { t } = useI18n();
  const toast = useToast();

  const apiError = ref<string | null>(null);

  // HELPERS
  function showRefusal(summary: string) {
    apiError.value = summary;

    toast.add({
      severity: 'error',
      summary,
      life: TOAST_LIFE_MS,
    });
  }

  // EVENTS
  function onLoginError(error: LoginApiError) {
    const STATUS = error?.response?.status;
    const DATA = error?.response?.data;

    /*
     * This API answers a refused login with an i18n KEY, in `message` or in the first entry of
     * the `errors` bag — `auth.login.invalidCredentials` or `auth.accountInactive`, not a sentence.
     * Both spellings appear depending on whether the refusal came from validation or from the
     * controller.
     */
    const MESSAGE_KEY = DATA?.message ?? (DATA?.errors && Object.values(DATA.errors)[0]?.[0]);
    const IS_VALIDATION_ERROR = STATUS === HTTP_STATUS.UNPROCESSABLE_ENTITY && MESSAGE_KEY;

    /*
     * A 422 for wrong password and unknown account is the same key on purpose — do not invent a
     * friendlier split client-side. A deactivated account with the right password is a different
     * key (`auth.accountInactive`); translate whatever the API sent.
     */
    if (IS_VALIDATION_ERROR) {
      showRefusal(t(MESSAGE_KEY));
      return;
    }

    /*
     * Laravel's throttle replies `{ message: "Too Many Attempts." }` — English prose, not a key.
     * It gets its own branch so that sentence never reaches a screen.
     */
    if (STATUS === HTTP_STATUS.TOO_MANY_REQUESTS) {
      showRefusal(t('auth.login.tooManyAttempts'));
      return;
    }

    apiError.value = null;

    toast.add({
      severity: 'error',
      summary: t('errors.somethingWentWrong'),
      detail: t('errors.refreshAndContactAdmin'),
      life: TOAST_LIFE_MS,
    });
  }

  return { apiError, onLoginError };
}

export { useLoginError };
