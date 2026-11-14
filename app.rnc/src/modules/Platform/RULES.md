---
version: 1.4.0
origin: rnc
---

# RULES — Platform Module

> For AI agents. rnc-4 F4d, 14/11/2026.

The platform operator's console (rnc-4 R24, R25, D19) over the API's `Platform` module (F4a):
`/plataforma/escritorios` (+ `/platform/firms`, `platform.firms`) and `/plataforma/consentimento`
(+ `/platform/consent`, `platform.consent`), and since F4d the view-as log `/plataforma/acessos`
(+ `/platform/view-as-log`, on `platform.firms` like the API's routes, W84). Both keys are the firm-less operator's alone — the API
also asks the person (`platform.operator`, W53) — so the rail's "Plataforma" group and these routes
never show for anyone in a firm. A core surface: no `meta.module`. Generalises rnc-3's brands
console (`@BrandsModule`): the same list-and-dialog shape, the same contrast note.

## Intentional Decisions

- **One firm form, two modes** (`MAddEditFirmForm`, W57): without a firm it CREATES — name, slug and
  the manager's login, as `POST platform/firms` takes them; with one it edits the LOOK — names,
  tagline, four proof points (or none), colours with the live white-on-primary note and the palette
  strip. A new firm starts with the provisioner's colours and modules, so its look is edited from
  its row once it exists, not in the create call.
- **The slug's rules are the API's** (celer-03, "backend owns slugs"): the form shows the address the
  slug will answer on (`<protocol>//<slug>.<host>` — the operator is at the root) and the API's
  refusals on the field (`firms.slugInvalid` / `slugReserved` / `slugTaken`). Nothing reserved is
  listed in the SPA.
- **A password is answered once and lives in the form that asked** (`MOneTimePassword`): the new firm
  goes to the view at once (`onSuccess`, prepended — the dialog stays open on the password), the
  manager form hands nothing back (the row does not change). Closing the dialog drops it.
- **Modules: the whole list, dependencies named before sending** (`MFirmModulesForm`): core shown,
  never switched; the opt-in ones as switches; `channel` apart ("Só a plataforma"). A module on
  without what it needs (`PLATFORM_MODULE_DEPENDENCIES`, the API's `FirmModules::DEPENDENCIES`) says
  so beside its switch and the save waits; the API's `modules.missingDependency` is the backstop.
- **Refusals by code, never by sentence** (`PLATFORM_ERROR_KEYS`): the API's stable keys map to
  `platform.errors.*`; an unknown one goes through `translateError`.
- **The operator lands here** (`HomeView`, Home module): firm-less with `platform.is_operator` and the
  key → `router.replace` to the firms list.
- **Every answer replaces its row** — no refetch after a create, look or modules save.
- **The logo is saved on its own** (`MFirmLogoField`, R27): picked, checked the API's way (PNG, JPEG
  or WebP — `PLATFORM_LOGO_TYPES` — and ≤ 512 KB) so an SVG never leaves the browser, previewed, sent
  on "Enviar logo". The sides (≤ 1024 px) and a disguised file stay the API's (`platform.logoTooWide`,
  `platform.logoType`), named by code. Its answer replaces the row and the open form's firm
  (`onLogoChange`) without closing the dialog or touching the look's unsaved fields.
- **A logo is shown on this origin** (`toStorageAssetPath`, `@Helpers`): the API answers
  `APP_URL/storage/...`; the SPA asks `/storage/...` on its own host, which Vite proxies to the API
  (`vite.config.ts`) and the deploy's vhost serves per host (plan AM10). Needs `php artisan
storage:link` in api.rnc.
- **View-as is "Acessar como suporte"** (D20 — "Ver como" is rnc-3's brand preview): never offered on
  the platform firm. Start and "Sair" each read `auth/me` again (the API answers the session, not the
  user) and `useFirmBrand().reload()` — the root's session answers `public/firm` for the viewed firm
  while it lasts, so the back office paints that firm's palette and logo. Start lands on Home, which
  stays (no console redirect) while `platform.view_as` is set; "Sair" returns to the console.
- **The banner is `OViewAsBanner`, in `TheLocalOverlays`** — TheLayout's project-owned slot, so the
  synced layout is not edited. It sits in `<main>`'s column with `order-first`: in flow above the
  scroller, so it never scrolls away and never covers a row, on every back-office screen.
- **A "Sair" the API accepted ends the banner at once** (rnc-4 verify P2-S5): `useUserStore().endViewAs()` runs
  on the stop's answer, before `auth/me` and the brand are read again; a failure of either afterwards still goes
  to the console (whose guard reads the session) and never shows "could not leave". Only a refused stop keeps the
  banner and its error toast.
- **"Sair" reads the brand again whatever `auth/me` answers** (Chrome QA 5.21): the root's kept firm brand is the
  viewed firm's until then, and must not outlive the view-as.
- **A refused clipboard is said beside the password** (`MOneTimePassword`, rnc-4 verify P2-S6): no permission or
  no `navigator.clipboard` (an insecure origin) shows `platform.password.copyFailed` (`role="alert"`) — the
  password is shown only once, so a copy button that silently did nothing would be believed.
- **An offboarded firm is not accessed as support**: 422 `platform.viewAsOffboarded` (code `view_as_offboarded`,
  the API's verify batch) is in `PLATFORM_ERROR_KEYS` and shown beside the row like the other view-as refusals.

## Known Gaps

- **A firm's sub-brands (`brands`) are not edited from the console**; the operator reaches them
  inside a view-as, when the viewed firm has `brands` on.
- _Retired 14/11/2026 (rnc-4 F7, W93):_ "Opt-in modules are hidden from the rail during a view-as" — `auth/me`'s
  `platform.view_as` now carries the viewed firm's `modules` and the User store's `hasModule` reads them while a
  view-as is set, so the rail offers exactly what the viewed firm has on.
- _Retired 14/11/2026 (rnc-4 F4d):_ "No logo upload and no view-as yet" — F4c gave the routes and F4d
  the screens.
