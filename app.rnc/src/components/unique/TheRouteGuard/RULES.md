---
version: 1.3.1
origin: pendulum
based-on: 1.2.1
---

# RULES — TheRouteGuard

> For AI agents. Last updated: 2026-09-17.

## Purpose

Component-based navigation guard wrapping the app's `<RouterView>`. Owns all route-level concerns for authenticated views on hard navigation: session restore and two validation cases (unauthenticated, public route). Route permissions, soft navigation and request cancellation belong to `attachAuthGuard` (`helpers/`).

## Architecture

- **Hard navigation** (`onBeforeMount` → `onHardNavigation`): fired on first load/refresh. Checks existing auth state; if not authenticated, calls `GetMeService` to restore session from cookie. After session resolves (success or failure), runs `routeMiddleware()` and sets `canRender = true` only if the route is allowed.
- **Soft navigation is not handled here.** An `onBeforeRouteLeave` path was described in this file and does not exist in the component; in-app navigation (and the request cancellation that comes with it) is `attachAuthGuard`'s. Retired from this file for that reason.
- **`canRender` ref gates the slot**: children do NOT mount until session is verified. This prevents private views from firing their own `onMounted` API calls before auth is known.

## Intentional Decisions

- **On a firm host the firm is asked once, here** (rnc-4 R3, `onFirmHostCheck`): `useFirmBrand().load()`
  (`@Composables`, the host firm's one boot read, without the tab's sub-brand — rnc-4 D10) at the hard navigation, only when `currentFirmSlug()` names one. Its 404 `firms.notFound` sets
  `isFirmMissing`, and the guard renders `FirmNotFoundView` in place of the slot on every path — site,
  login, back office. Here and not as a route
  (the address stays what was typed). The route renders while the answer is on its way; a missing firm
  then replaces it. Any other failure leaves the route alone: an outage is not a missing firm.

- **`canRender` stays `false` on blocked routes**: if `routeMiddleware()` returns `false`, the slot never renders, avoiding a flash of private content before the redirect completes.
- **`GetMeService` fails into `onSessionProbeFailed`, never into an empty catch.** An HTTP failure was already handled by the API provider (logged; a 401 clears the session), so the guard adds nothing and `onSessionResolved()` sends the person to login via `.finally()`. Anything that is not an HTTP failure — an error thrown while storing the user — never reached the provider and goes to Sentry (`captureException`). The `.catch(() => {})` it replaced swallowed both; retired because the rule is "no empty catch" and the second kind was a bug nobody would ever see.
- **A redirect settles through `onRedirectSettled`.** An `aborted` or `duplicated` navigation failure is the router doing its job and is ignored explicitly (`isNavigationFailure`); any other failure, or a rejection from a guard that threw, is reported to Sentry. No toast: the screen stays gated either way.
- **`onSessionResolved` name**: called regardless of whether session fetch succeeded or failed — it means "we now know the session state, proceed with routing." It does NOT mean authentication was successful.
- **Who may see a route is read through `@Helpers`** (`isPublicRoute`, `isReachableSignedOut` from `routeAccess.helper`), the same predicates `attachAuthGuard` uses. The meta was read by hand here and there, and a rule in three copies is a rule one copy gets wrong.

`onRouteLeave` and "`cancelAllRequests()` before routing" were listed here and are not in the
component: request cancellation on navigation is `attachAuthGuard`'s. Retired from this file for that
reason.

## Validation Cases (`useValidationCases`)

- `unauthenticatedRouteCase()` — not authenticated + private route → push to `loginWithReturnTo(target)`: the login path of the active language, carrying `?redirect=` (no toast). It pushed a hard-coded `/entrar` before, which dropped the destination and ignored the language; the helper is the one `attachAuthGuard` uses.
- `publicRouteCase()` — authenticated + public route → push to `intendedPathFrom(target.query) ?? { name: 'home' }` (hard: silent; soft: toast). Same rule as the router guard, including the refusal of a destination that names another host.

`staffRouteCase()` and `meta.requiresStaff` were listed here and do not exist: route permissions are
`meta.permission`, enforced by `attachAuthGuard` (`helpers/`). Retired from this file for that reason.

## Route Meta Flags

- `meta.isPublic = true` — login/auth routes. Read (through `isPublicRoute` / `isReachableSignedOut`) by `publicRouteCase` and `unauthenticatedRouteCase`.
- `meta.isOpen = true` — answers everyone (the not-found screen); never redirected.
- Do not read either flag by hand — use the `routeAccess.helper` predicates.
- `meta.permission` — read by `attachAuthGuard`, not here.

## Wiring

`TheRouteGuard` wraps `<RouterView>` in `App.vue`:

```vue
<TheRouteGuard>
  <RouterView />
</TheRouteGuard>
```

Auth routes must carry `meta: { isPublic: true }`. Do NOT use the old `meta: { public: true }` key — `TheRouteGuard` checks `isPublic` specifically.

## Do Not

- Do not add a `catch` with a toast to the `GetMeService` chain — the redirect to login IS the error handling.
- Do not push a literal login path or `{ name: 'home' }` — use `loginWithReturnTo` / `intendedPathFrom` from `@Helpers`.
- Do not write `.catch(() => {})` anywhere here — name what is expected and report the rest.
- Do not remove `canRender` gating — doing so causes children to mount before auth is known.
- Do not move session restore back to `router.beforeEach` — this component deliberately replaces that pattern.
- Do not add more than three validation cases without updating both `useValidationCases.ts` and this RULES.md.
