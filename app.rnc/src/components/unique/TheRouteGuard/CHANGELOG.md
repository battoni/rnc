# Changelog — TheRouteGuard

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.1 — 2026-09-17

### Changed

- `useValidationCases` and `TheRouteGuard.vue` read `meta.isPublic` / `meta.isOpen` through
  `isPublicRoute` / `isReachableSignedOut` (`@Helpers`), the same predicates `attachAuthGuard` uses,
  instead of by hand (largatudoevai-077). No behaviour change.
- RULES: retired `onRouteLeave` and "`cancelAllRequests()` before routing", which the component does
  not have — cancellation belongs to `attachAuthGuard` (rnc-256).

## 1.2.0 — 2026-09-16

### Fixed

- A signed-out visitor is sent to `loginWithReturnTo(target)` — the login path of the active language, carrying `?redirect=` — instead of a hard-coded `/entrar` that dropped the destination (largatudoevai-077). A signed-in person on a public route goes to `intendedPathFrom(query) ?? home`, like the router guard.
- The two `.catch(() => {})` on `GetMeService` are `onSessionProbeFailed`: HTTP failures stay with the API provider, anything else is reported to Sentry. Redirects settle through `onRedirectSettled`: `aborted`/`duplicated` are ignored by name, the rest reported.

### Changed

- RULES: retired `staffRouteCase`, `meta.requiresStaff` and the soft-navigation path, which the component does not have.
- Spec: seven new cases (destination carried, destination followed, foreign host refused, aborted redirect ignored, broken guard reported, HTTP probe failure left to the provider, other probe failure reported).

## 1.1.0 — 2026-09-14

### Added

- A route with `meta.isOpen` renders for everyone and is never redirected: signed out it is not sent to the login screen, signed in it is not sent Home. Mirrors `attachAuthGuard`, for the hard-navigation path this component owns.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
