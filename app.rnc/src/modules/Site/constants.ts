import {
  BRAND_HEADER,
  BRAND_NOT_FOUND_CODE,
  BRAND_PALETTE_STORAGE_KEY,
  BRAND_PREVIEW_STORAGE_KEY,
  BRAND_QUERY,
  BRAND_SLUG_PATTERN,
  BRAND_STORAGE_KEY,
} from '@Composables';

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

/** The product landing's sections a link can land on (rnc-4 R28): the header's nav and the CTAs. */
const PRODUCT_ANCHORS = {
  beta: 'beta',
  faq: 'perguntas',
  howItWorks: 'como-funciona',
  modules: 'modulos',
} as const;

/**
 * The Reforma's dates the product landing states (facts of rnc-1 §2, LC 214/2025), each an i18n key
 * under `site.product.urgency.facts`. The dates are the law's and are written the same in every
 * language, so they are data here, not copy.
 */
const PRODUCT_FACTS = [
  { date: '2026', key: 'testYear' },
  { date: '01/01/2027', key: 'cbs' },
  { date: '2029–2033', key: 'transition' },
] as const;

/** What the hub does for a firm, in the order a lead walks it (R28): each `site.product.steps.<key>`. */
const PRODUCT_STEPS = [
  { icon: 'pi pi-check-square', key: 'diagnostic' },
  { icon: 'pi pi-calendar', key: 'booking' },
  { icon: 'pi pi-th-large', key: 'pipeline' },
  { icon: 'pi pi-chart-line', key: 'metrics' },
] as const;

/** The core every firm has (`FirmModules` core, R17) and the opt-ins the menu offers (`site.product.modules.items`). */
const PRODUCT_CORE_MODULES = ['site', 'diagnostic', 'booking', 'pipeline', 'metrics'] as const;

/** The network channel is left out: it is one network's, switched on by the operator alone. */
const PRODUCT_OPT_IN_MODULES = [
  'briefing',
  'chat',
  'assistant',
  'radar',
  'portfolio',
  'contracts',
  'fiscal',
  'partners',
  'brands',
] as const;

/** The questions a firm asks before joining, each `site.product.faq.items.<key>`. */
const PRODUCT_FAQ = ['setup', 'data', 'cbsDate', 'diagnostic', 'price', 'who'] as const;

/** The beta form's optional message, as `BetaSignupRequest` caps it. */
const BETA_MESSAGE_MAX_LENGTH = 1000;

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
  BETA_MESSAGE_MAX_LENGTH,
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
  PRODUCT_ANCHORS,
  PRODUCT_CORE_MODULES,
  PRODUCT_FACTS,
  PRODUCT_FAQ,
  PRODUCT_OPT_IN_MODULES,
  PRODUCT_STEPS,
  SECTOR_GRID_ANCHOR,
  SITE_ROUTE_NAMES,
  TRAFFIC_SOURCE_STORAGE_KEY,
  TRANSITION_YEARS,
  VISITOR_STORAGE_KEY,
  WHAT_CHANGES,
};
