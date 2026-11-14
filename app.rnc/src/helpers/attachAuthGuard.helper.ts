import type { NavigationGuardNext, RouteLocationNormalized, RouteLocationRaw, Router } from 'vue-router';
import { useGlobalAbortController } from '@Composables';
import { useUserStore } from '@UserModule';
import { intendedPathFrom, loginWithReturnTo } from './afterLogin.helper';
import { isOpenRoute, isPublicRoute } from './routeAccess.helper';

const HOME_NAME = 'home';
const NOT_FOUND_NAME = 'not-found';

function requiredPermission(to: RouteLocationNormalized): string | undefined {
  return (to.meta as { permission?: string }).permission;
}

/**
 * Whether this person may open this route at all.
 *
 * Enforcement lives here rather than in a `beforeEnter` per route, for two reasons. The cycle: a
 * shared guard helper has to reach the user store, the store's module barrel re-exports the very
 * route files that would import the helper, and the whole graph evaluates to `undefined` before
 * anything runs. A route declaring `meta.permission` is plain data and imports nothing.
 *
 * The blind spot: hiding a nav item is presentation, not protection — the URL still works. The
 * routes this bootstrap ships already DECLARE what they cost, and their own comments say the
 * declaration closes the route; without this function it closed nothing, and typing the address
 * opened a board the nav had been careful to hide. A metadata field that promises a guarantee
 * nobody enforces is worse than no field, because it stops anyone from looking for the guard.
 */
function isPermitted(to: RouteLocationNormalized, hasPermission: (permission: string) => boolean): boolean {
  const PERMISSION = requiredPermission(to);

  if (!PERMISSION) return true;

  return hasPermission(PERMISSION);
}

/**
 * Whether the person's firm has the module this route belongs to (rnc-4 R17, `meta.module`, set in
 * `routes.local.ts`). A route of no module is core and always on.
 */
function isModuleOn(to: RouteLocationNormalized, hasModule: (key: string) => boolean): boolean {
  const MODULE = (to.meta as { module?: string }).module;

  return !MODULE || hasModule(MODULE);
}

/**
 * Where a screen of a module that is off goes: the not-found view at the SAME address (the API
 * answers 404 `modules.disabled` there too) — unless the person asked for a landing that redirects
 * into it (`/gestao` → the briefing) and that landing names a fallback (`meta.moduleFallback`): the
 * Gestão link must open something.
 */
function moduleOffDestination(to: RouteLocationNormalized): RouteLocationRaw {
  const FALLBACK = (to.redirectedFrom?.meta as { moduleFallback?: string } | undefined)?.moduleFallback;

  if (FALLBACK) return FALLBACK;

  return {
    name: NOT_FOUND_NAME,
    params: { pathMatch: to.path.slice(1).split('/') },
    query: to.query,
    hash: to.hash,
  };
}

/** A signed-in person's way in: the key the route costs, then the firm's module (the API's order, W36). */
function admitSignedIn(to: RouteLocationNormalized, next: NavigationGuardNext): void {
  const userStore = useUserStore();

  if (!isPermitted(to, userStore.hasPermission)) {
    next({ name: HOME_NAME });
    return;
  }

  if (!isModuleOn(to, userStore.hasModule)) {
    next(moduleOffDestination(to));
    return;
  }

  next();
}

export default function attachAuthGuard(router: Router): void {
  const { cancelAllRequests, createNewController } = useGlobalAbortController();

  router.beforeEach((to, from, next: NavigationGuardNext) => {
    const isGoingToDifferentRoute = to.name !== from.name;

    if (isGoingToDifferentRoute) {
      cancelAllRequests();
      createNewController();
    }

    if (isOpenRoute(to)) {
      next();
      return;
    }

    const userStore = useUserStore();

    if (isPublicRoute(to)) {
      // Authenticated users have no business on auth/public pages — send them home.
      if (userStore.user) {
        next(intendedPathFrom(to.query) ?? { name: HOME_NAME });
        return;
      }

      next();
      return;
    }

    if (userStore.user) {
      admitSignedIn(to, next);
      return;
    }

    // No prior session in this browser, so there is nothing for `/auth/me` to find: skip the
    // request rather than spend a round-trip being told what the hint already said.
    //
    // The comment used to end "and the 401 it would trigger", which was the real reason and is
    // no longer one: that 401 became a 500 because the API had no `login` route to redirect a
    // guest to, and this skip was the scar left by routing around it. The cause is fixed in
    // `bootstrap/app.php` — `redirectGuestsTo(fn () => null)` plus `shouldRenderJsonWhen` — so
    // what stays here stays on its own merit, which is one fewer request. A comment that names
    // a fixed bug as its reason is worse than no comment: it teaches the next reader to be
    // afraid of something that is not there.
    if (!userStore.hasSessionHint()) {
      next(loginWithReturnTo(to));
      return;
    }

    return userStore
      .fetchMe()
      .then(() => admitSignedIn(to, next))
      .catch(() => next(loginWithReturnTo(to)));
  });
}
