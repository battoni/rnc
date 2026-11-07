import { HTTP_STATUS } from '@Enums';
import { getLoginPath, REDIRECT_QUERY_KEY, translateError } from '@Helpers';
import { useUserStore } from '@UserModule';

function getTranslatedErrorMessage(error: any): string {
  const status = error.response?.status;

  // NOTE :: Validation errors
  const validationErrors = status === HTTP_STATUS.UNPROCESSABLE_ENTITY && error.response?.data?.errors;
  if (validationErrors) {
    const errors = error.response?.data?.errors;
    if (errors) {
      const firstError = Object.values(errors)[0] as string[] | undefined;
      if (firstError && firstError.length > 0 && firstError[0]) return translateError(firstError[0]);
    }
  }

  /*
   * Laravel's throttle middleware answers with `{ message: "Too Many Attempts." }`, which is not
   * an i18n key. Without a dedicated branch that English lands inside `errors.unexpected` and the
   * toast looks like a raw exception — applause that hits the reaction ceiling was the first
   * place this showed up, and every other 429 would have done the same.
   */
  if (status === HTTP_STATUS.TOO_MANY_REQUESTS) {
    return translateError('errors.tooManyAttempts');
  }

  /*
   * The envelope's own message, which is where almost every deliberate refusal from this API
   * actually lives.
   *
   * `ApiResponse::error()` writes `{ data: null, error: <message>, status_code }` — and every
   * named helper routes through it: `notFound`, `forbidden`, `conflict`, `badRequest`,
   * `unauthorized`, `internalError`, `serviceUnavailable`. That is 37 call sites in api.rnc today,
   * and the handler read `data.message`, which those responses do not have. The sentence the API
   * chose was dropped and the screen showed axios's own "Request failed with status code 404".
   *
   * Read at ANY status, deliberately. new.impressao scopes the same fix to 422 because that is
   * where its own errors land; here 422 is the one status this branch never sees, since Laravel
   * validation fills the `errors` bag handled above and `error()` defaults to 400.
   */
  const envelopeError = error.response?.data?.error;
  if (typeof envelopeError === 'string' && envelopeError) {
    return translateError(envelopeError);
  }

  // NOTE :: Other HTTP errors
  const otherHttpErrors = status && error.response?.data?.message;
  if (otherHttpErrors) {
    const errorMessage = error.response?.data?.message || error.message || 'errors.unexpected';
    return translateError(errorMessage);
  }

  // NOTE :: Network or other errors
  const errorMessage = error.message || 'errors.unexpected';
  return translateError(errorMessage);
}

/**
 * The request was called off, by us, on purpose.
 *
 * The route guard aborts everything in flight on every navigation, so this is the ordinary end of
 * any request the person outran — it is not a failure and there is nobody left to tell. Axios
 * reports it as `ERR_CANCELED`; a signal aborted before axios ever sends surfaces as a plain
 * DOMException, hence the second name.
 */
function isCanceledRequest(error: any): boolean {
  return error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError' || error?.name === 'AbortError';
}

/**
 * A session that ended has to end ON SCREEN too.
 *
 * Clearing the store alone left the page sitting there, drawn from data the server no longer
 * recognises, until the person happened to navigate — so a form could be filled in and submitted
 * against a session that was already gone. The destination travels along, so signing back in
 * returns them to the page they were on rather than to Home.
 *
 * The router is imported HERE and not at the top of the file. This module is reachable from the
 * router's own guard through the store, and evaluating that graph eagerly is the cycle the guard
 * describes; by the time a request has failed, everything is loaded and the import is resolved.
 */
async function ejectToLogin() {
  const { default: router } = await import('@Libraries/router');
  const CURRENT = router.currentRoute.value;

  // Already on the login screen, or on any other page that never needed a session: there is
  // nowhere to eject to, and pushing would replace a half-typed form with itself.
  if (CURRENT.meta?.isPublic || CURRENT.meta?.isOpen) return;

  const LOGIN_PATH = getLoginPath();
  const IS_WORTH_REMEMBERING = CURRENT.fullPath !== '/' && CURRENT.fullPath !== LOGIN_PATH;

  await router.push({
    path: LOGIN_PATH,
    query: IS_WORTH_REMEMBERING ? { [REDIRECT_QUERY_KEY]: CURRENT.fullPath } : undefined,
  });
}

async function errorHandler(error: any) {
  /*
   * Nothing below this line applies to a call-off: there is no status to read, no session to end,
   * and no sentence to translate. Logging it fills the console with the app's own tidying, and
   * `provider` drops the rejection before any view can toast it.
   */
  if (isCanceledRequest(error)) {
    throw error;
  }

  const status = error.response?.status;
  const isValidationError = status === HTTP_STATUS.UNPROCESSABLE_ENTITY && error.response?.data?.errors;
  const isForwardedError = Boolean(error.config?.forwardError);

  const structuredError = {
    code: error.code,
    data: error.response?.data,
    message: error.message,
    method: error.config?.method,
    status: error.response?.status,
    url: error.config?.url,
  };

  if (!isValidationError && !isForwardedError) {
    // The one console call the codebase allows: an unhandled API error has to reach whoever
    // is debugging. Note what `structuredError` carries — method, status and URL, never the
    // request body, which is where a password would be.
    // eslint-disable-next-line no-console
    console.error('API Error', structuredError);
  }

  /*
   * 401 only. A 401 means the server does not know who you are, so the session in this tab is
   * already over and clearing it is telling the truth. A 403 means it knows exactly who you are
   * and this is not yours — the session is fine, and ending it turns "you cannot open this page"
   * into "you have been signed out", which is a worse answer to a smaller problem. Anyone who
   * touched a screen their role does not reach was thrown back to the login form.
   *
   * `[401, 403].includes(status)` came from the bootstrap and lifeshifter, chapaja and
   * largatudoevai all still carry it. new.impressao and lifeshifter each fixed their half
   * independently, which is the strongest signal this inventory knows how to emit.
   */
  if (status === HTTP_STATUS.UNAUTHORIZED) {
    useUserStore().clearUser();

    await ejectToLogin();
  }

  // NOTE :: Attach translated message to error for view consumption
  error.translatedMessage = getTranslatedErrorMessage(error);

  throw error;
}

export { isCanceledRequest };
export default errorHandler;
