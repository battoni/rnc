import type { RouteLocationNormalized } from 'vue-router';

/**
 * Who may see a route, read from its meta — once, for both guards.
 *
 * `attachAuthGuard` (soft navigation) and `TheRouteGuard` (hard navigation) each read
 * `meta.isPublic` / `meta.isOpen` by hand, in three places. A rule kept in three copies is a rule
 * that one copy eventually gets wrong, and the bug then shows on only one of the two paths.
 */
type RouteWithMeta = Pick<RouteLocationNormalized, 'meta'> | undefined;

/** A route for signed-out people — login, sign-up. A signed-in person is sent Home from it. */
function isPublicRoute(route: RouteWithMeta): boolean {
  return !!(route?.meta as { isPublic?: boolean } | undefined)?.isPublic;
}

/**
 * A route that answers everyone, signed in or not — the not-found screen. Neither of the other two
 * shapes fits it: a public route sends a signed-in person Home, a private one sends a signed-out
 * person to the login screen, and both replace a mistyped address with a page nobody asked for.
 */
function isOpenRoute(route: RouteWithMeta): boolean {
  return !!(route?.meta as { isOpen?: boolean } | undefined)?.isOpen;
}

/** Whether a signed-out person may stay on this route instead of being sent to the login screen. */
function isReachableSignedOut(route: RouteWithMeta): boolean {
  return isPublicRoute(route) || isOpenRoute(route);
}

export { isOpenRoute, isPublicRoute, isReachableSignedOut };
