# Changelog — Site Module

## 1.8.0 — 2026-11-14

### Added

- The product's own site on the root (rnc-4 F6b, R28, R29): `ProductLandingView` with `OProductHero`
  (product name, the CBS countdown, beta CTA, demo link), `OProductUrgency` (three dated facts, LC
  214/2025), `OProductHowItWorks`, `OProductModules`, `OProductBeta` (D23's price line) and
  `OProductFaq`; `MBetaSignupForm` on `GetBetaConsentService` / `SignUpForBetaService`; `productSite`
  (`loadHomeView`, `firmSiteOnly`, `scrollToSection`, `useProductSite`). `ThePublicLayout` has a
  product mode. Tested by `ProductLandingView.view.integration.spec`, `MBetaSignupForm.spec`,
  `productSite.spec` and `e2e/product/landing.spec`.

## 1.7.0 — 2026-11-14

### Changed

- Landing (rnc-4 F2c2, R15): the proof strip is the firm's — or the sub-brand's — `proof`, and a firm with none shows no strip; `PROOF_POINTS` (Franco's four, with "RNC") removed. `?marca=principal` or the host firm's slug is the main brand (was `?marca=franco`).

## 1.6.0 — 2026-11-14

### Changed

- The brand composables moved to `src/composables/brand/` (rnc-4 F2b, D10) — `publicBrand`,
  `brandPalette`, `useBrandTheme` (now also `useSubBrandTheme`) and the `BRAND_*` constants — and are
  re-exported here. `PublicFirm` moved to `@Interfaces` (with `is_platform`, `logo_url`, `platform`) and
  is re-exported. `usePublicFirm` takes the main brand's firm and `isFirmMissing` from `useFirmBrand`.
- `ThePublicLayout` calls `useSubBrandTheme` (the firm's palette is `App.vue`'s) and writes the firm's
  `logo_text` in the mark.

## 1.5.0 — 2026-11-13

### Added

- `usePublicFirm().isFirmMissing` (rnc-4 F1b, R3): set by a 404 `firms.notFound` on the firm read.

### Changed

- `recordEvent`: on a firm host the event goes by keepalive `fetch` with `X-Firm` instead of the
  beacon, which cannot carry a header (rnc-4 W10).

## 1.4.1 — 2026-11-07

### Fixed

- Under a sub-brand, the footer's mark, tagline and copyright and the landing's kicker and proof strip showed Franco's words until `GET public/firm` answered; only the header's mark waited (rnc-3 review S1, W116). `usePublicFirm` exposes one `isBrandPending` that `ThePublicLayout` and `LandingView` read; the layout's own flag is gone. Tested by `ThePublicLayout.spec.ts` and `LandingView.view.integration.spec.ts` (the answer held back).

## 1.4.0 — 2026-11-07

### Added

- Sub-brands on the site (rnc-3 F2, R2, R3; AM21, C1): `publicBrand.ts` — `currentBrand()` reads `?marca=` into `sessionStorage` (`app.rnc:brand`, `?marca=franco` drops it), `isBrandPreview()` (`?previa=1`), `forgetBrand()`, and `publicApi()`, the one way a public call leaves (Site and Diagnostic services): it sends `X-Brand`, and on a 404 `brands.notFound` drops the brand (storage and address bar) and asks the same call again as the main brand. `brandPalette()` and `contrastRatio()` (pure): the `--color-primary-*` ramp from primary/accent in Franco's ramp shape, the accent and the hero tints lightened until they read. `useBrandTheme()`: the ramp inline on `<html>` while a public page is up, cached for the tab so the next page paints it on mount, handed back one second after the last public page goes. Tests: `usePublicFirm.spec.ts`, `brandPalette.spec.ts`, `ThePublicLayout.spec.ts`, `LandingView` and `e2e/site/brands.spec.ts` (A2, A6).

### Changed

- `usePublicFirm` asks again when the tab's brand changed since its last answer. `PublicFirm` carries `brand`, `short_name`, `tagline`, `proof`. The landing's kicker is the brand's tagline and its proof strip the brand's four points under a sub-brand; Franco's own copy stays on the main brand.

## 1.3.1 — 2026-11-07

### Changed

- Second pass on the first impression (rnc-1): the hero grid is 1.1fr/0.9fr and `lg:py-20`, so at
  1440×749 the two buttons share a row and the countdown's top sits above the fold (hero 934px →
  808px, countdown top 795px → 685px); the headlines of the landing and the sector page are
  semibold, not bold; the preview card is one density tighter (`p-5`, rows `py-2.5`) and its foot
  keeps the text and the button on one row; the sector cards drop the cyan `border-l-4` for a 1px
  ring that turns brand on hover — with the stripe they read as notices, like the news line.

## 1.3.0 — 2026-11-07

### Added

- The motion after the demo (rnc-1): the hero assembles on mount (kicker, headline word by word via
  the new `AWordRise` atom, lede, buttons, countdown, then the picture of the product, which floats,
  leans toward the pointer and rolls its count); the proof figures roll up (`v-count-up`); every
  band below the fold rises into view (`v-reveal`, staggered cards); the "o que muda" and sector
  cards lift and carry a spotlight; the timeline bars thicken in. The sector page's hero and facts
  land in sequence and its facts read as a timeline on a phone. `AWordRise.spec.ts`.

### Changed

- `MDiagnosticPreview` takes `enterIndex` (where the hero's words end) so the card follows the copy.
- The navy bands carry a cyan glow and a faint dot grid; section headers an eyebrow rule; the
  closing band a larger radius and relief. Hover on a CTA lifts it.

## 1.2.0 — 2026-11-07

### Changed

- `useTracking().track(type, sector, leadToken)` sends a diagnostic step's `lead_token` (AM2), and `visitContext()` exposes the visitor and source (rnc-1 F3).

### Removed

- The `/diagnostico` placeholder view and its `site.diagnostic` keys: the Diagnostic module owns the route now.

## 1.1.0 — 2026-11-07

### Added

- The landing (hero, `MCbsCountdown`, proof points, "o que muda", `MReformaTimeline` 2026–2033, the sector grid, the closing CTA) and `MDiagnosticPreview` (rnc-1 F2, R5).
- `SectorView` at `/reforma/:slug` (R6) with its dated facts, sources, CTA and the other sectors; the `/diagnostico` placeholder (W12).
- Services `GetPublicFirmService`, `GetSectorPagesService`, `GetSectorPageService`, `RecordEventService` (beacon); composables `usePublicFirm`, `useTracking` (R7), `useSeo` (R8).
- E2E `e2e/site/site.spec.ts` (A3, plus 390/1440 px overflow).

### Changed

- `SiteRoutes` moved to the module's `routes.ts` and gained the sector and diagnostic routes; route names in `SITE_ROUTE_NAMES`.

## 1.0.0 — 2026-11-07

### Added

- The module, `SiteRoutes` (`/`, `site-home`, open to everyone) and a placeholder `LandingView` in `ThePublicLayout` (rnc-1 F1, R3, AM15).
