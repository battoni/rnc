import { ref } from 'vue';
import { captureException } from '@sentry/vue';
import { isAxiosError } from 'axios';
import { storeToRefs } from 'pinia';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { isNavigationFailure, NavigationFailureType, useRouter } from 'vue-router';
import type { NavigationFailure, RouteLocationNormalized } from 'vue-router';
import { intendedPathFrom, isPublicRoute, isReachableSignedOut, loginWithReturnTo } from '@Helpers';
import { useUserStore } from '@UserModule';
import { NAVIGATION_MODES } from './enums';

/**
 * A redirect that was superseded or pointed at the page already open is the router doing its job:
 * the guard pushes while the router may be resolving another navigation, and a second push to the
 * same place is refused as a duplicate. Neither is worth anyone's attention.
 */
const EXPECTED_NAVIGATION_FAILURES = NavigationFailureType.aborted | NavigationFailureType.duplicated;
const HOME_NAME = 'home';

const navigationMode = ref<NAVIGATION_MODES>();
const targetRoute = ref<RouteLocationNormalized>();
const isRedirecting = ref(false);

export default function useValidationCases() {
  const { isAuthenticated } = storeToRefs(useUserStore());

  const ROUTER = useRouter();
  const { t } = useI18n();
  const toast = useToast();

  // HELPERS
  function toggleRedirectState() {
    isRedirecting.value = !isRedirecting.value;
  }

  // EVENTS
  /**
   * Anything else that stops a redirect is a bug — a guard that threw, a failure type the router
   * grew — and it goes to the error tracker. No toast: the screen stays gated either way, and the
   * person has nothing to act on.
   */
  function onRedirectError(error: unknown) {
    captureException(error);
  }

  function onRedirectSettled(failure: NavigationFailure | void | undefined) {
    if (!failure) return;

    if (isNavigationFailure(failure, EXPECTED_NAVIGATION_FAILURES)) return;

    onRedirectError(failure);
  }

  /**
   * The session probe failed, so the person is treated as signed out — `onSessionResolved` runs
   * either way and sends them to the login screen. An HTTP failure has already been handled by the
   * API provider (logged, and a 401 clears the session); a toast here would add nothing but a red
   * banner over the login form. What the provider never saw — an error thrown while storing the
   * user — is a bug, and it is reported instead of vanishing.
   */
  function onSessionProbeFailed(error: unknown) {
    if (isAxiosError(error)) return;

    onRedirectError(error);
  }

  function publicRouteCase(): boolean {
    const VALID_CASE = isAuthenticated.value && isPublicRoute(targetRoute.value);

    if (!VALID_CASE) return true;

    toggleRedirectState();

    if (navigationMode.value === NAVIGATION_MODES.SOFT) {
      toast.add({ severity: 'error', summary: t('common.error'), life: 5000 });
      toggleRedirectState();
      return false;
    }

    // Where the login screen was asked to send them — the same rule `attachAuthGuard` applies.
    ROUTER.push(intendedPathFrom(targetRoute.value?.query ?? {}) ?? { name: HOME_NAME })
      .then(onRedirectSettled)
      .catch(onRedirectError)
      .finally(toggleRedirectState);

    return false;
  }

  function unauthenticatedRouteCase(): boolean {
    const VALID_CASE = !isAuthenticated.value && !isReachableSignedOut(targetRoute.value);

    if (!VALID_CASE) return true;

    toggleRedirectState();

    // The login path in the person's language, carrying the destination so signing in returns there.
    ROUTER.push(loginWithReturnTo(targetRoute.value))
      .then(onRedirectSettled)
      .catch(onRedirectError)
      .finally(toggleRedirectState);

    return false;
  }

  return {
    NAVIGATION_MODES,
    isRedirecting,
    navigationMode,
    targetRoute,

    onSessionProbeFailed,
    publicRouteCase,
    unauthenticatedRouteCase,
  };
}
