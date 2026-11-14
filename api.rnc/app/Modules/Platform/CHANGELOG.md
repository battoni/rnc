# Changelog — Platform Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-11-14
- RULES: the "No SPA yet" Known Gap retired — the console's screens (rnc-4 F4b, app `Platform` module) give every refusal key its text.

## 1.0.0 — 2026-11-14
- The operator's console API (rnc-4 F4a, R24, R25, A26–A28): `PlatformFirmController` — `GET platform/firms` (every firm with its lead count, one grouped query), `POST platform/firms` (`FirmProvisioner`), `PATCH platform/firms/{id}` (the look, `brand.lowContrast` under 4,5:1), `PUT platform/firms/{id}/modules` (`FirmModulesService`, R18), `POST platform/firms/{id}/manager` (create or reset, the password once); `PlatformConsentController` — `GET/PUT platform/consent-default` (must hold `{escritorio}`). Behind `platform.firms` / `platform.consent` and `EnsurePlatformOperator` (the firm-less `operador`). `PlatformFirmRepository` (the one place `FirmScope` is lifted, for counts), `PlatformFirmService`, the requests and `PlatformFirmResource`, `ProvisionedFirmResource`, `ManagerLoginResource`. Tested by `tests/Feature/Platform/OperatorSignInTest`, `PlatformFirmsTest`, `ConsentDefaultTest` and `tests/Feature/Access/Phase4AccessTest`.
