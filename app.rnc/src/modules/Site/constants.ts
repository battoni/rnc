/** The site's route names — what another module links to (`celer-05`, "Reaching another module's screen"). */
const SITE_ROUTE_NAMES = {
  diagnostic: 'site-diagnostic',
  home: 'site-home',
  sector: 'site-sector',
} as const;

/** The landing's anchor for the sector grid, which the header's "Por setor" scrolls to. */
const SECTOR_GRID_ANCHOR = 'setores';

/**
 * 01/01/2027, local midnight: the CBS is charged in full (facts.md §2). The countdown stays on this
 * date even with the request to delay the CBS to 2028 — that is news "em discussão", not the law.
 */
const CBS_START = new Date(2027, 0, 1);

/** The anonymous visitor id (a UUID v4), first-party, kept across visits (R7). */
const VISITOR_STORAGE_KEY = 'rnc:visitor';

/** The source this visit arrived from, kept for the session so later pages carry it too. */
const TRAFFIC_SOURCE_STORAGE_KEY = 'rnc:traffic-source';

/** `utm_medium` values a paid campaign uses. */
const PAID_MEDIUMS = ['ads', 'cpc', 'cpm', 'display', 'paid', 'paid-social', 'paid_social', 'ppc'];

/** `utm_medium` values a partner's link carries (the network's referrals, Phase 2). */
const PARTNER_MEDIUMS = ['affiliate', 'parceiro', 'partner'];

/** The longest `utm_medium` the API stores. */
const MEDIUM_MAX_LENGTH = 60;

/**
 * The sub-brand the tab is shown under (rnc-3 R2, D2): `?marca=<slug>` once, then kept for the tab in
 * `sessionStorage` and sent as `X-Brand` on every public call. `?marca=franco` is the main brand.
 */
const BRAND_STORAGE_KEY = 'app.rnc:brand';

/** "Ver como" (rnc-3 R5): `?previa=1` beside `?marca=` marks the tab as the operator's preview. */
const BRAND_PREVIEW_STORAGE_KEY = 'app.rnc:brand-preview';

/** The brand's colours as last answered, so the next page of the visit paints them before the API does. */
const BRAND_PALETTE_STORAGE_KEY = 'app.rnc:brand-palette';

/** The address-bar words of the sub-brand (R2, R5). */
const BRAND_QUERY = { brand: 'marca', main: 'franco', preview: 'previa', previewOn: '1' } as const;

/** The header every public call carries under a sub-brand, and the API's refusal of a stale one (C1). */
const BRAND_HEADER = 'X-Brand';
const BRAND_NOT_FOUND_CODE = 'brands.notFound';

/** The shape of a slug the API could know (`firm_brands.slug`, 40): anything else is never sent. */
const BRAND_SLUG_PATTERN = /^[a-z0-9-]{1,40}$/;

/** The landing's proof points (R5, facts.md §1): the figure, then the i18n key of its caption. */
const PROOF_POINTS = [
  { figure: '35 anos', key: 'years' },
  { figure: '500+', key: 'clients' },
  { figure: 'ISO 9001', key: 'iso' },
  { figure: 'RNC', key: 'network' },
] as const;

/** "O que muda" (facts.md §2): the five changes, each an i18n key under `site.landing.changes`. */
const WHAT_CHANGES = ['cbs', 'ibs', 'selective', 'splitPayment', 'credit'] as const;

/**
 * The 2026–2033 transition (facts.md §2, LC 214/2025 arts. 343–349): the share of today's ICMS and
 * ISS that is still charged each year, in tenths — the bar the timeline draws. 10 until 2028, then
 * 9, 8, 7 and 6 from 2029 to 2032, and none in 2033.
 */
const TRANSITION_YEARS = [
  { legacyTenths: 10, year: 2026 },
  { legacyTenths: 10, year: 2027 },
  { legacyTenths: 10, year: 2028 },
  { legacyTenths: 9, year: 2029 },
  { legacyTenths: 8, year: 2030 },
  { legacyTenths: 7, year: 2031 },
  { legacyTenths: 6, year: 2032 },
  { legacyTenths: 0, year: 2033 },
] as const;

export {
  BRAND_HEADER,
  BRAND_NOT_FOUND_CODE,
  BRAND_PALETTE_STORAGE_KEY,
  BRAND_PREVIEW_STORAGE_KEY,
  BRAND_QUERY,
  BRAND_SLUG_PATTERN,
  BRAND_STORAGE_KEY,
  CBS_START,
  MEDIUM_MAX_LENGTH,
  PAID_MEDIUMS,
  PARTNER_MEDIUMS,
  PROOF_POINTS,
  SECTOR_GRID_ANCHOR,
  SITE_ROUTE_NAMES,
  TRAFFIC_SOURCE_STORAGE_KEY,
  TRANSITION_YEARS,
  VISITOR_STORAGE_KEY,
  WHAT_CHANGES,
};
