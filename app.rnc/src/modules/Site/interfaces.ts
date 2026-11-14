import type { TRACKING_EVENT_TYPE, TRAFFIC_SOURCE } from './enums';
// The host firm's resource moved to `@Interfaces` with the brand composables (rnc-4 D10); the
// Site module keeps re-exporting it, so `PublicFirm` still comes from `@SiteModule`.
import type { PublicBrandProof, PublicFirm, PublicFirmHours } from '@Interfaces';

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
