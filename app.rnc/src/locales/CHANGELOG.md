# Changelog — Locale File Conventions

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.19.0 — 2026-11-14

### Added

- rnc-4 verify P2-S2: post-date twins `site.landing.titleStarted`, `site.seo.landingDescriptionStarted`, `site.product.seo.descriptionStarted`, `site.product.urgency.titleStarted`, `site.product.closing.textStarted` (read through `cbsKey`).
- rnc-4 verify P2-S6: `platform.password.copyFailed`.
- rnc-4 verify (API batch): `platform.errors.viewAsOffboarded`.

## 1.18.2 — 2026-11-14

### Fixed

- rnc-4 verify P1-S1: the host firm's names are stored as literals (`setFirmNames`), so a `|`, `@`, `{` or `}`
  in a name prints as typed. Known gap recorded: an empty name prints its linked key until the firm answers.

## 1.18.1 — 2026-11-14

### Added

- rnc-4 F9b: `site.privacy.*` (the privacy notice), `site.footer.privacy`, `diagnostic.contact.privacy`,
  `pipeline.lead.data.*`, `pipeline.lead.timeline.events.anonymised` / `anonymisedOnExit`, and the API's
  `firms.offboarded` refusal (F9a) as a top-level `firms` key in `local/*.json`.

## 1.18.0 — 2026-11-14

### Added

- rnc-4 F4d: `platform.logo.*`, `platform.viewAs.*` (banner, "Sair"/"Leave", the log and its reasons), `platform.errors.logo*` / `viewAs*`, `navigation.platformViewAsLog`.

## 1.17.0 — 2026-11-14

### Added

- `local/*.json` (rnc-4 F5b): `firmSettings.*` (the four settings screens), `navigation.firmContact|firmTeam|firmSpecialists|firmConsent`, and the two API refusals the screens show by key — `users.form.roleNotAssignable` (W76) and `leads.consentPlaceholderMissing` (W79), merged into the core `users` and a new `leads` section.

## 1.16.0 — 2026-11-14

### Changed

- `local/*.json` (rnc-4 F5a): `rolesAndPermissions.groups.firm` for the new `firm.settings` key on the roles screen; `site.footer.credentialsText` removed — the credentials line is the firm's own field (W31).

## 1.15.0 — 2026-11-14

### Added

- `local/*.json` (rnc-4 F4b): the `platform` group — the console's firms list, firm form, modules, manager login, one-time password and default consent, and the refusals by the API's code (`platform.errors.*`); `navigation.platform`, `platformFirms`, `platformConsent`. `scripts/sort-locales.mjs` registers `platform`.

## 1.14.0 — 2026-11-14

### Added

- `local/*.json` (rnc-4 F4a): `rolesAndPermissions.groups.platform`, `rolesAndPermissions.permissions.consent` (the console's two keys on the roles screen) and `rolesAndPermissions.operatorOnlyKey` (the API's refusal to give a platform key to another role). The console's own strings arrive with its screens (F4b).

## 1.13.0 — 2026-11-14

### Changed

- `local/*.json` (rnc-4 F2c2, R14, R15): every string that named Franco takes `{firmShort}` (short name) or `{firmName}` (full name), filled at runtime by the host firm; "Reinaldo" is "Gestão" / "Management" (`navigation.management`, `home.workspaces.management.title`, the partner registry's empty state, the NF-e note); `site.landing.proof.*` removed (the proof strip is the firm's own `proof`); `site.landing.closingText` and `auth.brand.domain` generic.

## 1.12.0 — 2026-11-14

### Changed

- Keys `navigation.ceo` and `home.workspaces.ceo` → `navigation.management` and `home.workspaces.management` (rnc-4 F2c1, R14); their wording is F2c2's.

## 1.11.0 — 2026-09-21

### Added

- `users.cannotManageHigherAccount` — the refusal when somebody edits, archives or deletes an
  account that holds more than they do.

## 1.10.0 — 2026-09-21

### Added

- `rolesAndPermissions.administratorKeepsEveryKey` — the refusal when somebody unticks a key on the
  Administrator profile.

## 1.9.0 — 2026-09-20

### Added

- `rolesAndPermissions.cannotGrantUnheldPermission` and `cannotAssignUnheldRole`: the two refusals
  the API answers when somebody tries to hand out more than they hold (ARC-03).

## 1.8.0 — 2026-09-17

### Added

- `users.form.usernameCheckUnavailable`, `users.loadError.{retry,title}`, `tips.errors.{anchorTaken,contentRequired,reasonRequired}` (moved from `api.rnc/lang/*/tips.php`, now that the API sends keys) and `ideas.merge.{alreadyMerged,sameIdea,targetUnavailable}` (the keys the API sends for a refused merge), in both locales. Order checked against `sort-locales.mjs`.
- `common.actions.close` (the icon-only close buttons of `ONavbarDrawer` and `MDefaultModal`), `common.paginator.report` (`OPaginator`'s page report, PrimeVue's placeholders passed through) and `errors.{copy,copied}` (`AFormError`'s copy button), in both locales. Order checked against `sort-locales.mjs`.

## 1.7.0 — 2026-09-17

### Added

- `sort-locales.mjs` warns (`! <file>: not in TOP_LEVEL_GROUPS…`) for any top-level key it has to guess.

### Fixed

- `TOP_LEVEL_GROUPS` registers every top-level key. `imageUpload` (MImageUpload) and `theme` (AColorSchemeToggle) are components and now sort with them; `features`, `ideas`, `news`, `notFound`, `personalization`, `showcase` and `tips` are registered as modules, where they already fell by default. Both locale files re-sorted.

### Changed

- `showcase.components.sample.inventory`: office stock (`ballpointPens`, `notebooks`, `officeChairs`, `usbCables`; `electronics`, `furniture`, `office`) replaces the print-shop sample (`a4Sulfite`, `blackToner`, `coated150`, `vinylBanner`; `ink`, `paper`, `vinyl`). `showcase.components.units.sheets` → `units.items`.

## 1.6.0 — 2026-09-16

### Added

- `address.*` — the labels, placeholders and lookup status lines of `MAddressFields` and
  `MLocalityFields` (from chapaja, which said "ZIP" in English; here it is "CEP (postal code)").
  Registered as a COMPONENT group in `scripts/sort-locales.mjs`.
- `showcase.components.sections.address` / `.locality` and the two section descriptions.

## 1.5.0 — 2026-09-16

### Added

- `navigation.docs`, for the documentation entry `VITE_DOCS_URL` switches on.
- `personalization.visualCard.language`. The language names themselves are not translated — a
  reader should not have to recognise their own language spelled in a foreign one.

## 1.4.0 — 2026-09-14

### Added

- `notFound.*` — the not-found screen (from lifeshifter).
- `users.form.roleUnknown` — a role the catalogue does not know, distinct from "role is required" (from lifeshifter).

### Added

- `users.cannotDeleteSelf` — the API's refusal to let an account delete itself.

### Fixed

- `rolesAndPermissions.update` existed only in pt-BR.

## 1.3.0 — 2026-09-14

### Added

- `common.noItems`, `auth.accountInactive`, `auth.flow.signUp.invalidCpf`, `auth.flow.signUp.verify.awaitingApproval`, `users.detail.{role,username,email,phone}`, `users.emailVerified`, `users.emailUnverified`, `users.form.cannotChangeOwnStatus`, `users.form.cannotDeactivateSelf`, `auth.flow.signUp.{phone,phonePlaceholder,phoneInvalid,phoneDescription}` (en, pt-BR).

## 1.2.0 — 2026-09-10

### Added

- `news.actions.saveDraft` (en, pt-BR).

## 1.1.1 — 2026-07-13

### Added

- `common.tip` key for default `ATip` aria-label.

## 1.1.0 — 2026-07-13

### Added

- Personalization namespace and `navigation.personalization` keys for en and pt-BR.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
