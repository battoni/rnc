---
version: 1.0.0
origin: rnc
---

# RULES — Platform Module

> For AI agents. rnc-4 F4b, 14/11/2026.

The platform operator's console (rnc-4 R24, R25, D19) over the API's `Platform` module (F4a):
`/plataforma/escritorios` (+ `/platform/firms`, `platform.firms`) and `/plataforma/consentimento`
(+ `/platform/consent`, `platform.consent`). Both keys are the firm-less operator's alone — the API
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

## Known Gaps

- **No logo upload and no view-as yet** (F4c, F4d): the console edits text and colours; a firm's
  sub-brands (`brands`) are not edited here until view-as.
