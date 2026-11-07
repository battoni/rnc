---
version: 1.12.0
origin: pendulum
based-on: 1.12.0
---

# RULES — src/helpers/

> For AI agents. Last updated: 2026-09-21.

## Purpose

Shared pure utility functions with no Vue reactivity. Helpers are plain TypeScript — no `ref`, no `computed`, no lifecycle hooks.

## Helper vs. Composable vs. Store — Decision Guide

| Question                                             | Answer →                                          |
| ---------------------------------------------------- | ------------------------------------------------- |
| Pure function, no Vue reactivity, no side effects?   | Helper (`src/helpers/`)                           |
| Uses `ref`, `computed`, `watch`, or lifecycle hooks? | Composable (`src/composables/`)                   |
| Manages global shared state?                         | Store                                             |
| Used in only one module?                             | Module helper (`src/modules/ModuleName/helpers/`) |

## Current Helpers

| File                                   | Purpose                                                                                                                            |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `afterLogin.helper.ts`                 | `intendedPathFrom` (the `?redirect=` to follow) and `loginWithReturnTo` (the login path carrying it)                               |
| `attachAuthGuard.helper.ts`            | Registers a global router `beforeEach` guard that checks auth state                                                                |
| `bodyScrollLock.helper.ts`             | Toggles `overflow-hidden` on `document.body`                                                                                       |
| `routeAccess.helper.ts`                | `isPublicRoute` / `isOpenRoute` / `isReachableSignedOut` — who may see a route, read from its meta, for both guards                |
| `routeId.helper.ts`                    | `toRouteId` — a route param/query value as the integer id the API sends (`NaN` when it names nothing)                              |
| `slotContent.helper.ts`                | `hasSlotContent` — did a slot's rendered nodes actually draw anything (a false `v-if` leaves a Comment, a `<template>` a Fragment) |
| `withExternalLinks.helper.ts`          | `withExternalLinks` — every `<a>` in sanitised rich text gets `target="_blank" rel="noopener noreferrer"`, as a pair; idempotent   |
| `localDate.helper.ts`                  | `toLocalDateString` / `toLocalDateTimeString` / `fromDayMonthYear` — a day on the person's own calendar                            |
| `sentenceCase.helper.ts`               | Capitalizes the first character of a localized `Intl` label — never CSS `capitalize`                                               |
| `i18n/attachLocalePathGuard.helper.ts` | Rewrites EN↔PT route names so the URL matches the active locale                                                                    |
| `i18n/getI18nRouteName.helper.ts`      | Resolves a localized route name with fallback to base name                                                                         |
| `i18n/getLoginPath.helper.ts`          | Locale-aware login path (`/entrar` vs `/login`)                                                                                    |
| `i18n/translateError.helper.ts`        | Translates an error string via i18n; falls back to `errors.unexpected` template                                                    |

## Adding a localized route means adding its pair (CRITICAL)

`attachLocalePathGuard` keeps the address bar in the active locale. Routes named with the
`-en` / `-pt-BR` suffix are paired by a rule and need nothing. **Auth routes are not** — their
twins are spelled out (`auth.login` / `auth.entrar`, `auth.onboarding` / `auth.completarCadastro`),
and no rule can derive one spelling from the other.

Those pairs live in `AUTH_ROUTE_PAIRS` inside the helper, once, with both directions derived from
that single list. **A new auth route that is not added there silently keeps whichever language was
typed** — which is how the entire sign-up, verification, support and terms path stayed English in a
Portuguese app while `/login` rewrote correctly. `attachLocalePathGuard.helper.spec.ts` asserts the
pairs from the outside, so a half-added route fails the suite rather than someone's address bar.

Every candidate is checked with `routeExists` before `next({ name })`. A name nothing registers
aborts the navigation, which turns a wrong-language URL into a dead one.

## Every exit to the login screen goes through `loginWithReturnTo`

The router guard, `TheRouteGuard` and the API provider's 401 all send a person to the login screen,
and each must carry `?redirect=` and use the path of the active language. `TheRouteGuard` pushed a
hard-coded `/entrar` and dropped the destination; the function lives beside `intendedPathFrom` now so
the leaving and the returning half are one rule in one file.

## Route meta is read through `routeAccess.helper`

`attachAuthGuard` (soft navigation) and `TheRouteGuard` (hard navigation) both decide who may see a
route. They read `meta.isPublic` / `meta.isOpen` by hand in three places; the predicates live in
`routeAccess.helper.ts` now, and neither guard reads the flags directly.

## A day is serialized with local getters, never `toISOString()`

`toISOString()` answers in UTC: in São Paulo it is already tomorrow from 21:00, and a typed day
(local midnight) is already yesterday anywhere east of UTC. Use `toLocalDateString` /
`toLocalDateTimeString`. A spec for anything that serializes a date sets the zone (`vi.stubEnv('TZ', …)`) to one
where the two disagree — under UTC they agree and the wrong one passes.

## Conventions

- File name: `camelCase.helper.ts`
- Default export for single-function files; named exports for multi-function files
- Exported from `src/helpers/index.ts` barrel
- Imported via `@Helpers` alias
- Sub-folders allowed for domain grouping (e.g. `i18n/`)

## Money and Brazil

- **Money is an integer of cents.** The API sends and stores `*_cents`; a screen renders with a
  formatter and converts the one float an `InputNumber mode="currency"` produces with `toCents`.
  No arithmetic on money happens in floats.
- **A country's rules live in its own directory.** `helpers/brazil/` (`formatReal`), with
  `services/brazil/` (ViaCEP, IBGE), `composables/brazil/` (`useCepLookup`),
  `components/molecules/brazil/` (`MAddressFields`, `MLocalityFields`),
  `constants/brazil.constant.ts` (`BRAZIL_STATES`) and `interfaces/brazil.interfaces.ts`
  (`AddressFields`, `LocalityFields`, `ViaCepAddress`). The products this bootstrap serves are
  Brazilian, so it ships; a product elsewhere deletes those files and the build names every
  TypeScript consumer. Templates are the exception — an auto-imported component that is gone only
  warns at runtime, so search for the two molecules by name (`components/RULES.md`).

## Ids are numbers

Every API resource sends ids as integers. A route param and a query value are strings, so a screen
converts them with `toRouteId` where it reads them and compares numbers from then on — never
`String(id)` on the other side of the comparison.

## A slot that exists is not a slot that renders

`!!slots.actions` answers "did the parent pass one", never "is there anything on THIS row". A slot
whose every action failed its own `v-if` renders a Comment, so a list that lit its ⋮ from slot
presence showed an empty menu. `hasSlotContent(slots.actions?.({ … }))` asks the rendered output
instead, and it walks Fragments because the content is almost always inside a `<template>`.

## Do Not

- **Do not use Vue reactivity** (`ref`, `computed`, etc.) in helpers — use a composable instead.
- **Do not put router or i18n setup** here — only utilities that consume them after setup.
- **Do not add module-specific helpers here** — only promote when used across 2+ modules.
- **Do not decide whether a slot has content from `!!slots.name`** — use `hasSlotContent` on what it
  rendered for that item.
