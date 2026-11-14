# Changelog — Platform Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.0 — 2026-11-14
- View-as refuses an offboarded firm (rnc-4 verify P2-A3): `ViewAsException::offboarded()` (422
  `platform.viewAsOffboarded`, code `view_as_offboarded`) on start; `current()` ignores a row whose firm was
  offboarded mid-visit. A sign-in over a live view-as closes it as `logout` (P2-A4). Tested in `ViewAsTest`.

## 1.1.2 — 2026-11-14
- RULES: the "logged IP is the direct peer" Known Gap retired (rnc-4 F7) — `TRUSTED_PROXIES` (`config/trustedproxy.php`) makes the audit row's address the client's behind the deploy's proxy; the runbook carries the vhost's `/storage` (AM10).

## 1.1.1 — 2026-11-14
- RULES: the "`/storage` is outside `/api`" Known Gap retired (rnc-4 F4d) — the SPA asks `/storage` on its own host and Vite proxies it; the production vhost stays F7's (AM10).

## 1.1.0 — 2026-11-14
- Logo upload and view-as (rnc-4 F4c, R26, R27, A41, A42): `FirmLogoController` — `POST/DELETE platform/firms/{id}/logo` (`UploadLogoRequest` + `Rules/LogoImage`: PNG/JPEG/WebP by `finfo` and the image header, ≤ 512 KB, ≤ 1024 px; stored by Firm's `FirmLogoService`); `PlatformFirmResource` gains `logo_url`. `ViewAsController` — `POST platform/firms/{id}/view-as`, `DELETE platform/view-as`, `GET platform/view-as-log`; the module's first table `platform_view_as_sessions` (`Models/ViewAsSession`, `Enums/ViewAsEndReason`, `ViewAsSessionRepository`), `ViewAsService` (the session key, `current()` read by Firm's `ViewAsFirm`), `ViewAsException` (409 / 422 with a code), `Listeners/CloseViewAsOnSignIn` (`expirou`). Known Gaps "until F4c…" and "no logo upload yet" retired. Tested by `tests/Feature/Platform/FirmLogoTest`, `ViewAsTest` and `tests/Feature/Access/Phase4AccessTest`.

## 1.0.1 — 2026-11-14
- RULES: the "No SPA yet" Known Gap retired — the console's screens (rnc-4 F4b, app `Platform` module) give every refusal key its text.

## 1.0.0 — 2026-11-14
- The operator's console API (rnc-4 F4a, R24, R25, A26–A28): `PlatformFirmController` — `GET platform/firms` (every firm with its lead count, one grouped query), `POST platform/firms` (`FirmProvisioner`), `PATCH platform/firms/{id}` (the look, `brand.lowContrast` under 4,5:1), `PUT platform/firms/{id}/modules` (`FirmModulesService`, R18), `POST platform/firms/{id}/manager` (create or reset, the password once); `PlatformConsentController` — `GET/PUT platform/consent-default` (must hold `{escritorio}`). Behind `platform.firms` / `platform.consent` and `EnsurePlatformOperator` (the firm-less `operador`). `PlatformFirmRepository` (the one place `FirmScope` is lifted, for counts), `PlatformFirmService`, the requests and `PlatformFirmResource`, `ProvisionedFirmResource`, `ManagerLoginResource`. Tested by `tests/Feature/Platform/OperatorSignInTest`, `PlatformFirmsTest`, `ConsentDefaultTest` and `tests/Feature/Access/Phase4AccessTest`.
