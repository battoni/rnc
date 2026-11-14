import type { BETA_CLIENTS_BAND, TRACKING_EVENT_TYPE, TRAFFIC_SOURCE } from './enums';
// The host firm's resource moved to `@Interfaces` with the brand composables (rnc-4 D10); the
// Site module keeps re-exporting it, so `PublicFirm` still comes from `@SiteModule`.
import type { PublicBrandProof, PublicFirm, PublicFirmHours } from '@Interfaces';

/** `GET public/beta-signups/consent` — the words the beta form asks consent under (rnc-4 W61). */
interface BetaConsent {
  text: string;
  version: number;
}

/** What `POST public/beta-signups` validates (`BetaSignupRequest`), key for key. */
interface BetaSignupPayload {
  city: string;
  clients_band: BETA_CLIENTS_BAND;
  company: string;
  consent: true;
  email: string;
  medium: string | null;
  message: string | null;
  name: string;
  phone: string;
  role: string;
  source: TRAFFIC_SOURCE;
  uf: string;
}

/** `PublicBetaSignupResource` — an allow-list: the firm's name for the success state and the channel. */
interface BetaSignupResult {
  channel: string;
  company: string;
}

/** `PublicSectorPageSummaryResource` — a card of the landing's sector grid. */
interface SectorPageSummary {
  label: string;
  lede: string;
  slug: string;
  title: string;
}

/** `PublicSectorPageResource` — one sector page, whole. */
interface SectorPage extends SectorPageSummary {
  facts: SectorFact[];
  seo: { description: string; title: string };
}

interface SectorFact {
  date: string;
  source: { label: string; url: string | null };
  text: string;
}

/** What `POST /api/public/events` validates (`RecordEventRequest`), key for key. */
interface TrackingEventPayload {
  lead_token?: string | null;
  medium: string | null;
  path: string;
  sector?: string | null;
  source: TRAFFIC_SOURCE;
  type: TRACKING_EVENT_TYPE;
  visitor: string;
}

/** The source of a visit, as the site derives it from the URL and the referrer. */
interface TrafficOrigin {
  medium: string | null;
  source: TRAFFIC_SOURCE;
}

/** The anonymous visitor and the source of the visit, as every event and the diagnostic send them. */
interface VisitContext extends TrafficOrigin {
  visitor: string;
}

/** What the browser knows about how the visitor arrived. */
interface ArrivalContext {
  host: string;
  referrer: string;
  search: string;
}

/** Search-engine metadata a public page sets for itself (R8). */
interface SeoMeta {
  description: string;
  title: string;
}

export type {
  ArrivalContext,
  BetaConsent,
  BetaSignupPayload,
  BetaSignupResult,
  PublicBrandProof,
  PublicFirm,
  PublicFirmHours,
  SectorFact,
  SectorPage,
  SectorPageSummary,
  SeoMeta,
  TrackingEventPayload,
  TrafficOrigin,
  VisitContext,
};
