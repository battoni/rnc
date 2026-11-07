import type { TRACKING_EVENT_TYPE, TRAFFIC_SOURCE } from './enums';

/** `PublicFirmResource` — the firm's brand and public contact, as the API sends it. */
interface PublicFirm {
  address: string | null;
  brand_accent: string;
  brand_primary: string;
  business_hours: PublicFirmHours[];
  logo_text: string;
  mailbox: string | null;
  name: string;
  phone: string | null;
  phone_href: string | null;
  slug: string;
}

/** ISO weekdays (1 = Monday) and the opening hours on them. */
interface PublicFirmHours {
  from: string;
  to: string;
  weekdays: number[];
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
  PublicFirm,
  PublicFirmHours,
  SectorFact,
  SectorPage,
  SectorPageSummary,
  SeoMeta,
  TrackingEventPayload,
  TrafficOrigin,
};
