# Changelog — Platform Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-11-14

### Added

- Logo and view-as (rnc-4 F4d, R26, R27, A43): `MFirmLogoField` in the look form (PNG/JPEG/WebP ≤ 512 KB checked before sending, an SVG never sent, preview, "Enviar logo", "Remover logo", the API's refusals by code — `platform.logo*`); "Acessar como suporte" on every row but the platform firm's (`StartViewAsService` → `auth/me` → brand reload → Home; `platform.viewAs*` named beside the row); `OViewAsBanner` (mounted by `TheLocalOverlays`, "Sair" → `StopViewAsService` → `auth/me` → brand reload → console); `ViewAsLogView` at `/plataforma/acessos` (+ `/platform/view-as-log`, `platform.firms`, 25 a page). `PlatformFirm.logo_url`, `ViewAsSession`; five services. Tests: `MFirmLogoField.spec.ts`, `OViewAsBanner.spec.ts`, `ViewAsLogView.view.integration.spec.ts`, `FirmsView` view-as cases, `e2e/platform/view-as.spec.ts`.

## 1.0.0 — 2026-11-14

### Added

- The platform console (rnc-4 F4b, R24, R25): `FirmsView` at `/plataforma/escritorios` (`platform.firms`) — every firm with its subdomain link, modules, creation date and leads, "Novo escritório", and per row the look, the modules and the manager login; `ConsentDefaultView` at `/plataforma/consentimento` (`platform.consent`) — the default consent, refused without `{escritorio}` (C15). Molecules `MAddEditFirmForm` (create / look with the contrast note), `MFirmModulesForm` (core, opt-in, operator-only, dependency message), `MFirmManagerForm` (create or reset by username, W54), `MOneTimePassword` (shown once, with copy). Services for the seven `platform/*` routes. Tests: `MAddEditFirmForm.spec.ts`, `MFirmModulesForm.spec.ts`, `MFirmManagerForm.spec.ts`, `FirmsView.view.integration.spec.ts` (A29), `ConsentDefaultView.view.integration.spec.ts`, `e2e/platform/console.spec.ts` (A30).
