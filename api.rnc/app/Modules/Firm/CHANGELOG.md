# Changelog — Firm Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.12.0 — 2026-11-14

### Added

- The privacy notice's data and a firm's exit (rnc-4 F9a, R38, R40): `GET public/privacy`
  (`PublicPrivacyController`, `PublicPrivacyResource`); `FirmOffboarder` + `firm:offboard <slug> --confirm`
  (`OffboardedFirm` DTO); `firms.offboarded_at` (folded into `create_firms`), `Firm::isOffboarded()`.
  An offboarded firm is no tenant (`FirmResolver`), nobody belongs to it, a session of its people is
  refused 403 `firms.offboarded` (`ResolveCurrentFirm`), `forEachFirm` skips it. Tested by
  `tests/Feature/Firm/PublicPrivacyTest`, `tests/Feature/Firm/OffboardFirmCommandTest`.

## 1.11.0 — 2026-11-14

### Added

- The logo's writer and view-as (rnc-4 F4c, R26, R27): `FirmLogoService` (`replace` / `remove`, `sniff`,
  `EXTENSIONS`) stores the console's upload on `public` under a server-made name and deletes the file it
  replaced; `ViewAsFirm` answers the firm of the operator's open view-as through Platform's
  `ViewAsService::current` (it returned null since F1a). Known Gap "until F4c…" retired. Tested by
  `tests/Feature/Platform/FirmLogoTest` and `ViewAsTest`.

## 1.10.0 — 2026-11-14

### Added

- The manager's firm settings (rnc-4 F5a, R30): `FirmSettingsController` (`GET/PATCH firm/settings`,
  `firm.settings`), `UpdateFirmSettingsRequest`, `UpdateFirmContactDTO`, `FirmSettingsResource`,
  `FirmSettingsService::current` / `updateContact`. `firms.credentials` (W31), on `Firm` and in
  `PublicFirmResource`. Tested by `tests/Feature/Firm/FirmSettingsTest`.

## 1.9.0 — 2026-11-14
- For the platform console (rnc-4 F4a, R25): `FirmProvisioner::provideManager()` (a username of the firm's manager gets a new password; a free one becomes a new `gestao` in that firm; anyone else's is 422 `users.form.usernameTaken` and left as it was) and `DTOs/FirmManagerDTO`, `DTOs/ManagerLogin`; a slug or username lost to a racing request is 422 `firms.slugTaken` / `users.form.usernameTaken`, no longer a 500. `Services/FirmLookService` + `DTOs/UpdateFirmLookDTO` (the only writer of a firm's look); `PlatformSettings::updateConsentDefault()` + `PlatformSettingRepository::updateOrCreatePlatformSetting()`; `Rules/ReadableUnderWhite` (the 4,5:1 check, now shared by `UpdateBrandRequest`); `MailBrand::siteUrlOf()` public. Tested by `tests/Feature/Platform/*`.

## 1.8.0 — 2026-11-14
- `public/firm` answers `modules` (rnc-4 F3c, R17): the firm's opt-in list (`FirmModules::enabled`, under a sub-brand too), so the site hides what is off — the chat. Tested in `tests/Feature/Firm/PublicFirmTest`.

## 1.7.0 — 2026-11-14
- `firm:create` (rnc-4 F3b1, R21, D17): `Services/FirmProvisioner` makes a firm ready to sign in to in one transaction — default palette and weekday hours, core + `briefing` through `FirmModulesService`, the six default sector pages (`SectorPageService::startFirm`), consent v1 from the platform default, a `gestao` manager with a 16-character password returned once (`--specialist` makes them bookable); refuses a malformed, reserved or taken slug, a missing name or manager, a taken username and a malformed or taken e-mail (`firms.slug*`, `users.form.*`). `DTOs/CreateFirmDTO`, `DTOs/ProvisionedFirm`, `FirmRepository::createFirm()`; the command is `app/Console/Commands/CreateFirm.php`. Tested by `tests/Feature/Firm/CreateFirmCommandTest`.

## 1.6.0 — 2026-11-14
- Modules per firm (rnc-4 F3a, R16–R20): `Helpers/FirmModules` (catalogue), `Services/FirmModulesService` (the only writer: unknown, dependency, operator-only `channel`), `CurrentFirm::hasModule()` / `forEachFirmWithModule()`, `FirmRepository::updateFirmModules()`, `Resources/FirmSummaryResource` (`auth/me`'s `firm`); `PublicFirmResource::LOGO_DISK` is public. Tested by `tests/Unit/Modules/Firm/FirmModulesServiceTest`, `tests/Feature/Firm/ModuleGateTest`, `ModuleCommandsTest`, `tests/Feature/Auth/MeFirmTest`.

## 1.5.0 — 2026-11-14

### Added

- `firms.short_name`, `tagline`, `proof`, `logo_path`, `modules` (folded into `create_firms_table`); `PublicFirmResource` answers them with `logo_url`, `is_platform` and `platform.name` (rnc-4 F2a, R8, R9). Tested by `tests/Feature/Firm/PublicFirmBrandTest`.
- `MailBrand` — the firm an e-mail wears, from-name, reply-to and the firm's own site (R12, D11). Tested by `tests/Feature/Mail/FirmMailBrandTest`.
- `platform_settings` table, `PlatformSetting`, `PlatformSettingRepository`, `PlatformSettings` (`consent_default`, D12).

### Changed

- Seeders: Franco gets its short name and the landing's proof; the platform firm its short name and the consent default; Alvorada its invented short name, tagline and proof.

## 1.4.0 — 2026-11-13

### Added

- `firms.is_platform`, `FirmRepository::findPlatformFirm()`, `FirmResolver` (tenant by slug or host, reserved slugs, `belongsTo`), `ViewAsFirm` (null until F4c), `config/platform.php`, `PlatformFirmSeeder` (every environment) and `DemoFirmSeeder` (local only) — rnc-4 F1a, D2, D3, D18.

### Changed

- `ResolveCurrentFirm`: a header naming another firm is 403 `firms.mismatch` (D5); a firm-less session works in the platform firm or is 403 `firms.required` (AM1); reserved and platform slugs are 404 (AM9); the default firm is `hub` (`phpunit.xml` pins `franco`, D6). Tests: `FirmByHostTest`, `FirmMismatchTest`, `PlatformSeedersTest`.

## 1.3.2 — 2026-11-07

### Changed

- `BrandRepository::findActiveBrandBySlug` (the public `X-Brand` lookup) filters by `CurrentFirm` itself, not by the `BelongsToFirm` scope alone, and finds nothing without a current firm (rnc-3 review A1). Tested by `tests/Feature/Firm/BrandRepositoryFirmTest`, which lifts the scope off `FirmBrand`.

## 1.3.1 — 2026-11-07

### Added

- `BrandService::all()` — the firm's sub-brands by name, inactive too, for the board's brand filter (rnc-3 F2).

## 1.3.0 — 2026-11-07

### Added

- Sub-brands (rnc-3 F1, R1–R5): `firm_brands` table, `FirmBrand` model and factory, `CurrentBrand` scoped binding, `ResolveCurrentBrand` middleware (alias `brand`, `X-Brand` looked up inside the request's firm; 404 `brands.notFound`, `brand:fallback` on the events beacon — AM21), `Helpers/Contrast` (WCAG 2), `BrandRepository`, `BrandService`, `UpdateBrandRequest` (`#rrggbb`, four proof points, `brand.lowContrast` under 4,5:1), `UpdateBrandDTO`, `BrandResource`, `MainBrandResource`, `BrandController`: `GET brands`, `PATCH brands/{id}`, `POST brands/{id}/activate|deactivate` (`brands.manage`). Tests: `ContrastTest`, `PublicBrandTest` (A1), `BrandAdminTest` (A5), `Phase3AccessTest` (A25 brand rows).

### Changed

- `PublicFirmResource` gains `brand`, `short_name`, `tagline` and `proof` (null for the main brand) and, under a sub-brand, its name, logo text and colours over the firm's own contact (`underBrand()`). `PublicFirmTest` updated.

## 1.2.0 — 2026-11-07

### Added

- The network's money model (rnc-2 F4, R11, D10): `firms.partner_money_model` (default `comissao`), `partner_commission_rate_bp` (1000) and `partner_monthly_fee_cents` (49000), folded into `create_firms`; `PartnerMoneyModel` enum (`other()`), `Firm::partnerMoneyModel()`, `FirmRepository::updateFirm`, and `FirmSettingsService::updatePartnerMoneyModel` — the only writer, on the current firm. Tested by `tests/Feature/Partner/LedgerTest` (the switch moves only the signed-in person's firm, whatever `X-Firm` says).

## 1.1.0 — 2026-11-07

### Added

- `GET /api/public/firm` (`PublicFirmController`, `PublicFirmResource`) — the public site's brand and contact (rnc-1 F2, R5). Test: `PublicFirmTest`.

### Removed

- The `trait.unused` PHPStan entry for `BelongsToFirm`: F2's `SectorPage`, `Visitor` and `TrackingEvent` use the trait.

## 1.0.0 — 2026-11-07

### Added

- `firms` table, `Firm` model and factory, `FirmSeeder` (Franco Contabilidade, firm 1, from facts.md §1) (rnc-1 F1, R1).
- `CurrentFirm` scoped binding, `ResolveCurrentFirm` middleware (session → `X-Firm` → `franco`), `FirmScope` and the `BelongsToFirm` trait (D1, plan AM6). Tests: `FirmScopeTest` (A1), `FirmSeederTest`.
