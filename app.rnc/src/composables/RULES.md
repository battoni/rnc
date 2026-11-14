---
version: 1.8.0
origin: pendulum
based-on: 1.6.0
---

# RULES — src/composables/

> For AI agents. Last updated: 2026-09-17.

## Purpose

Shared Vue composables — functions that use Vue reactivity APIs (`ref`, `computed`, `watch`, lifecycle hooks) and are reused across multiple components or modules.

## Composable vs. Helper vs. Store — Decision Guide

| Question                                                            | Answer →                                                  |
| ------------------------------------------------------------------- | --------------------------------------------------------- |
| Does it use `ref`, `computed`, `watch`, or lifecycle hooks?         | Composable (`src/composables/`)                           |
| Is it pure logic with no Vue reactivity?                            | Helper (`src/helpers/`)                                   |
| Does it manage global shared state that persists across components? | Store (`src/stores/` or module `store.ts`)                |
| Is it only used within one module?                                  | Module composable (`src/modules/ModuleName/composables/`) |

## `usePaginatedList` — the server-pagination contract

Every server-paginated list view uses it; do not hand-roll page state in a view.

- It owns `items`, `page`, `perPage`, `total`, `loading` and the `{ page, per_page }` fetch. The
  view passes the list service and an `onError`, renders `items`, and wires `<OPaginator>`.
- **Row changes go through `removeItem(id)` / `prependItem(item)`, never `items.value = …`.** On a
  paginated list the row count and `total` have to move together — writing `items` directly leaves
  the pager offering a page that no longer exists. Replacing a row in place (an edit) is still a
  plain `items.value = items.value.map(…)`: the count does not change.
- A filter or sort change resets to page 1 before reloading (`page.value = 1; load()`).
- **A create on a FILTERED list reloads page 1 instead of `prependItem`.** The new row was not in
  the search, and prepending it puts a row that does not match the filter above rows that do —
  it reads as a broken search. Judge "filtered" by what the last request SENT, not by what the
  person has picked and not yet searched (`UsersView`'s `appliedSearch`). Unfiltered, prepend.
- **`loadFailed` tells a failed load from an empty list.** It is `true` after a rejected fetch and
  `false` again the moment the next `load()` starts. A view renders a "did not load" state with
  **Try again** (`load`) BEFORE its empty state — otherwise a 500 reads as "nothing here yet"
  (celer-07; `UsersView` is the reference).
- **`load()` returns its promise**, so a caller that refreshes after a save can wait for it and
  report its outcome instead of firing and forgetting.
- It requires `T extends { id: number }` — `removeItem` matches on `id`.
- `perPage` defaults to 15, the API's `Pagination::DEFAULT_PER_PAGE`; the server clamps whatever
  is asked for.

## `useCepLookup` — one CEP lookup at a time (`brazil/`)

- `lookupCep(cep)` resolves to ViaCEP's address or `null`, and keeps `isLookingUp` and
  `lookupFailed` for the status lines. It never rejects: the service underneath never throws.
- **A lookup a newer one overtook resolves to `null` and changes no state.** Without this a slow
  answer for the first CEP typed fills the form after the second.
- `lookupFailed` does not separate "unknown CEP" from "ViaCEP unreachable": the person's next step
  is the same — type the address.
- It lives in `composables/brazil/` with the rest of Brazil's removable files. Consumers:
  `MAddressFields`, `MLocalityFields`.

## `useLocale` — the only writer of the language

- `setLocale` switches vue-i18n, **applies PrimeVue's own catalogue** (`applyPrimeVueLocale` over
  `usePrimeVue().config.locale`), persists the choice and mirrors it on `<html lang>`. PrimeVue never
  reads vue-i18n: a switch that skipped its catalogue left the DatePicker in "August / Sun" until a
  reload. It therefore needs PrimeVue installed, as the app and both test harnesses do.
- The language the app BOOTS in is mirrored on `<html lang>` by `libraries/i18n`, not here.

## `useCountdown` — a countdown by the wall clock

Every "resend in 42s" uses it; do not count ticks in a component.

- It stores a deadline and derives `remainingSeconds` from `Date.now()` on each tick and on
  `visibilitychange`. A phone suspends `setInterval` while the person is in another app reading the
  code, and a countdown that decremented per tick came back still locked long after the minute it
  promised.
- `start(seconds)` restarts (never stacks a second interval); it stops by itself at zero and when
  the calling scope is disposed.
- Start it in `onBeforeMount` when mounting means a code was just sent, so the first frame already
  shows the button disabled.
- A spec fakes only `['Date', 'setInterval', 'clearInterval']` and models a suspension by moving
  the clock (`vi.setSystemTime`) without running the interval.

## `brand/` — the host firm's brand on both layouts (rnc-4 D10)

- **`useFirmBrand`** reads `GET public/firm` once per page load, WITHOUT `X-Brand`: the back office of
  a firm is the firm's, whatever sub-brand the tab last browsed. It keeps the answer in
  `sessionStorage` (`app.rnc:firm-brand`) and STARTS from it, so a second page paints the firm's
  colours, logo text and `platform.name` before the API answers. `isFirmMissing` is its 404
  `firms.notFound` (R3, read by `TheRouteGuard`); any other failure is forgotten and asked again.
- **`useBrandTheme()` is mounted once, by `App.vue`**, and paints the firm's ramp (`brandPalette`) on
  `<html>` for both layouts; `useSubBrandTheme(firm)` is `ThePublicLayout`'s and lays a sub-brand's
  ramp over it only while a public layout is up, handing back after the route fade. One painter, two
  layers: `paintCurrent()` is the only writer of `--color-primary-*` on the root. The document title is
  `platform.name` wherever no public page is up (a public page writes its own, `useSeo`).
- **`publicBrand`** (`?marca=`, `X-Brand`, `publicApi`) and the `BRAND_*` constants moved here from the
  Site module with it; the Site module re-exports them, so `@SiteModule` still answers for them.
- **The firm's names fill the translations** (rnc-4 R14): `useBrandTheme` calls `setFirmNames`
  (`@Libraries/i18n`) with the host firm's `short_name || name` and `name`, behind every `{firmShort}` /
  `{firmName}` in `locales/local`. The host firm's on both layouts — a sub-brand changes the colours and
  the mark, not the firm the copy speaks for. Until a firm is known the names are empty.
- **`?marca=principal` or the host firm's own slug is the main brand** (`publicBrand`).
  _Retired 14/11/2026 (rnc-4 F2c2):_ "`?marca=franco` is the main brand" — one tenant's slug in the build.
- The palette's colours come from the firm's row, never the theme file: `VITE_ACTIVE_THEME=hub` is
  only the neutral ground of a tab's first page. A firm with no colours keeps the theme's.

## Conventions

- File name: `useCamelCase.ts` (always `use` prefix)
- Exported as a named function: `export function useFoo() { ... }`
- Exported from the barrel at `src/composables/index.ts`
- Imported via `@Composables` alias

## Do Not

- **Do not add pure utility functions** (no reactivity) — those belong in `src/helpers/`.
- **Do not manage global persistent state** in a composable — use a Pinia store instead.
- **Do not put module-specific composables here** — only add when used across 2+ modules.
