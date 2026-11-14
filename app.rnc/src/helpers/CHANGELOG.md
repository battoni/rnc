# Changelog — src/helpers/

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.15.0 — 2026-11-14

- `attachAuthGuard` closes a route of a module the firm has off (rnc-4 F3c, R17): `meta.module` → the not-found view at the same address; a landing that redirected there names `meta.moduleFallback` (`/gestao` → metrics without `briefing`). Order: the key, then the module (the API's, W36).

## 1.14.0 — 2026-11-13

### Added

- `firmHost.helper.ts` (rnc-4 F1b, R2): `firmSlugFromHost(host, rootDomain)`, `currentFirmSlug()`
  (the page's own host against `ROOT_DOMAIN`) and `rootSiteUrl(location, rootDomain)`. The API
  provider and the events beacon name the firm with it; `FirmNotFoundView` links to the root.

## 1.13.0 — 2026-11-07

### Added

- `prefersReducedMotionHelper()` — whether the system asks for less motion, read at the moment it
  matters (never cached at module load) and `false` where `matchMedia` is missing, so motion plays
  rather than throws. The site's directives read it (rnc-1).

## 1.12.0 — 2026-09-21

### Added

- `hasSlotContent(nodes)` — whether a slot's rendered output draws anything: Comment nodes (what a
  false `v-if` leaves), blank text and empty Fragments count as nothing, and Fragments are walked
  because slot content almost always sits inside a `<template>`. The list organisms decided "this
  row has actions" from `!!slots.actions`, which is true even when every action in the slot failed
  its own `v-if` — an empty ⋮ on the row, and an empty footer in the detail dialog.

## 1.11.0 — 2026-09-17

### Added

- `withExternalLinks.helper.ts` (from new.impressao 03c4a47): adds `target="_blank" rel="noopener noreferrer"` to every link in sanitised rich text, replacing any stored `target`/`rel`. Idempotent. Used by `ATip`, `TipsView` and `MTipHistoryDialog` (tip-002). Spec ported, plus a case that `<abbr>`/`<aside>` are left alone.

## 1.10.0 — 2026-09-17

### Added

- `routeAccess.helper.ts`: `isPublicRoute`, `isOpenRoute` and `isReachableSignedOut`, exported from
  the barrel. `attachAuthGuard` lost its private copies and `TheRouteGuard` reads the same functions
  (largatudoevai-077). Covered by `routeAccess.helper.spec.ts`.

## 1.9.0 — 2026-09-16

### Added

- `toRouteId` (`routeId.helper.ts`): a route param or query value turned into the integer id the API sends, once, at the boundary. A value that is not a positive integer is `NaN` and matches nothing (from lifeshifter-269).

## 1.8.1 — 2026-09-16

### Changed

- `RULES.md` lists the Brazil address block among the country's removable files, and says that a
  deleted auto-imported component is found by search, not by the build.

- `localDate.helper.ts` — `toLocalDateString`, `toLocalDateTimeString` and `fromDayMonthYear` (refuses 31/02 instead of rolling it into March). A day is a local fact; `toISOString()` is UTC (lifeshifter-265, chapaja-272). Covered by `localDate.helper.spec.ts`, which runs in São Paulo.
- `loginWithReturnTo` exported from `afterLogin.helper.ts`, so `TheRouteGuard` uses the same exit as the router guard (largatudoevai-077).

### Changed

- `attachAuthGuard` imports `loginWithReturnTo` instead of defining it; behaviour unchanged, covered by its existing spec.

## 1.8.0 — 2026-09-16

### Added

- `formatBrazilPhone` — the stored `55…` number as a person reads it, for the phone-code sign-in.

## 1.7.0 — 2026-09-16

### Added

- `isUiLocale` — whether a value names a language this build ships. Every entry point for a locale
  is untrusted (query string, stored value from an older build, header), and applying one with no
  messages behind it leaves raw keys on every screen.

## 1.6.0 — 2026-09-14

### Added

- `attachAuthGuard` lets a route with `meta.isOpen` through before any session check. The not-found screen answers everyone: as a public route it sent a signed-in person Home, as a private one it sent a visitor to the login screen, and both replaced a mistyped address with a page nobody asked for (found porting the catch-all from lifeshifter, whose `isPublic` route has the first problem).
- `toSentenceCaseHelper` — the first character of a localized `Intl` label in uppercase and the rest untouched. CSS `capitalize` turns pt-BR "setembro de 2026" into "Setembro De 2026" (from lifeshifter).

## 1.5.0 — 2026-09-14

### Added

- `toCents` (money is integer cents) and `brazil/formatReal` (cents as reais), from chapaja's `money.helper`.
- `brazil/isValidBrazilPhone` — area code plus 8 or 9 digits.

## 1.4.0 — 2026-09-10

### Added

- The auth guard charges `meta.permission` again. The routes this bootstrap ships declare what
  they cost and their own comments say the declaration closes the route; nothing read it, so the
  nav hid the link and typing the address opened the screen anyway.

## 1.3.0 — 2026-09-09

### Changed

- `i18n/attachLocalePathGuard.helper.ts` — all nine EN↔PT auth route pairs are covered, from one list both directions derive; `onboarding`, `verifyEmail`, `support`, `forgotPassword`, `resetPassword` and `terms` were missing and stayed English inside a pt-BR app. Every candidate is now checked against the router before `next({ name })`.

## 1.2.0 — 2026-09-09

### Added

- `i18n/attachLocalePathGuard.helper.ts` — rewrites EN↔PT route names so the URL matches the active locale (with unit tests).

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`attachAuthGuard.helper.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
