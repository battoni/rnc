# Changelog — App Root (App.vue)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.4.0 — 2026-11-14

### Changed

- `App.vue` mounts `useBrandTheme()` (rnc-4 F2b, R10): the host firm's palette and `platform.name` on both layouts. RULES: a third deliberate exception, the old "two exceptions" retired with its reason.

## 1.3.1 — 2026-09-22

### Fixed

- `styles/components/touch-target.css`: a SMALL Select or MultiSelect grows 6px under a finger, like
  the small button beside it. It was missing from the list — drawn at 35px, the default 3px inset
  left 39, five short — and the rule has to be written LAST in that block, because `.p-select::before`
  above it matches a small select too and wins on being later. Found in lifeshifter, whose forms use
  `size="small"` on their selects: 62 of them on one screen, every one at 39px under a thumb.

## 1.3.0 — 2026-09-22

### Added

- `libraries/i18n/apiKeys.spec.ts`: every permission group the API declares in
  `permission-groups.local.php` has a translation in both locales. The API names a group by i18n key
  (`nameKey`), never by text, so a missing translation renders the key itself — largatudoevai's
  Perfis screen listed `rolesAndPermissions.groups.locations` and three more like it (Leonardo's QA,
  2026-09-22). `usedKeys.spec.ts` cannot see these: they are never written as `$t('…')` in the
  source. rnc's own file is an empty stub, so the guard is silent here and bites downstream.

## 1.2.0 — 2026-09-17

### Added

- `libraries/primevue/locale.ts`: the PrimeVue catalogue carries `aria` labels (paginator arrows,
  the dialog's close) and the DatePicker's navigation labels in `en` and `pt`, and
  `applyPrimeVueLocale` switches a live config without dropping PrimeVue's other `aria` keys
  (VIS-14, lifeshifter-428).
- `test/optimizeDeps.spec.ts`: every PrimeVue component a template renders is in
  `optimizeDeps.include`, resolved with the build's own `PrimeVueResolver`; `vite.config.ts` gained
  `primevue/iconfield` and `primevue/inputicon` (largatudoevai-099).
- `test/render.spec.ts` and `test/msw/handlers.spec.ts`: the harness itself is under test.

### Fixed

- `libraries/i18n` writes `<html lang>` at boot, not only on a switch — `index.html` ships `en` and
  the app boots in pt-BR or in the stored choice (largatudoevai-080).
- `ApiProvider` sends a boolean query value as `1`/`0`, the spelling Laravel's `boolean` rule
  accepts; `String(true)` was a 422 (lifeshifter-276).
- `test/render.ts`: the `ThePageHeader` stub renders `leading`, `titleSuffix`, `primaryAction` and
  `actions`, and the tooltip directive mirrors its text onto `data-tooltip`, as in `mount.ts`
  (lifeshifter-456, lifeshifter-457).
- `test/msw/handlers.ts`: `auth/users/check-username` is registered before `auth/users/:id`, which
  matched it first and made the default handler dead code (largatudoevai-100).
- `index.html`: a robots meta equal to the hosts' `X-Robots-Tag` (`.nginx` said it was there), the
  tile image by absolute path, and the tile comment names `--color-canvas`, not the brand colour
  (chapaja-348, chapaja-364, lifeshifter-446, lifeshifter-647).
- `enums/httpStatus.enum.ts`: `CONFLICT` (409); `IdeaView` compares with `HTTP_STATUS.NOT_FOUND`
  (largatudoevai-135).
- `.env.example`: `VITE_LOGIN_FLOW=username` is username + password, not an e-mail OTP; the e-mail
  code flow lives at `/login-email` (rnc-103).

### Changed

- RULES: a note at the top that this file is injected into every Vue/TS edit, so unit rules go in the
  unit's `RULES.md` and conventions in `.claude/rules/celer-*` (from lifeshifter-485). Purpose and
  "Do Not" name what `App.vue` actually holds — `TheRouteGuard` around the `RouterView` and
  `useLocaleFromQuery()` — as the two accepted exceptions (rnc-234).

## 1.1.6 — 2026-09-16

### Changed

- `test/msw/handlers.ts`: `mockRole.id` is an integer and the role handlers answer `Number(params.id)` — a route param is a string, the API id is not (from lifeshifter-269).

## 1.1.5 — 2026-09-16

### Added

- `App.vue` calls `useLocaleFromQuery()` once: the app has a single router and a single global
  locale, so the `?locale=` handoff is read here and nowhere else.

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

## 1.1.4 — 2026-09-16

### Changed

- RULES: a routed view has exactly one root node — `out-in` cannot drive a fragment-rooted child, and the app goes blank on leave with nothing logged. `src/test/routedViewSingleRoot.spec.ts` guards it.

## 1.1.3 — 2026-09-15

### Fixed

- Restore `mode="out-in"` and drop the absolute leave / `:key="route.fullPath"` experiment.
  Simultaneous fade + `position: absolute; inset: 0` on leave invented thin vertical and
  horizontal scrollbars on every settled page (DevTools 360×800). NotFound → Home stays fixed
  via named `push` in NotFoundView, not via this Transition.

## 1.1.2 — 2026-09-15

### Fixed

- Route-fade leave is `position: absolute; inset: 0` so, without `out-in`, the outgoing layout
  does not stay in flow beside the incoming one. Stacked `h-full` roots were inventing scrollbars
  on authenticated pages.

## 1.1.1 — 2026-09-15

### Fixed

- RouterView Transition keys the swapped component with `route.fullPath` and no longer uses
  `mode="out-in"`. Serial out-in from NotFound (CentredLayout fades the root) to Home (TheLayout
  fades a child) could finish the leave and never mount the enter — blank page until a hard reload.
  Simultaneous keyed fade keeps the rail steady and paints Home.

## 1.1.0 — 2026-07-13

### Added

- Boot-time `uiStore.init()` alongside `colorSchemeStore.init()`.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
