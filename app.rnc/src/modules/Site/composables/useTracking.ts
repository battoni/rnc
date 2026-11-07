import type { TRACKING_EVENT_TYPE } from '../enums';
import type { ArrivalContext, TrafficOrigin } from '../interfaces';
import {
  MEDIUM_MAX_LENGTH,
  PAID_MEDIUMS,
  PARTNER_MEDIUMS,
  TRAFFIC_SOURCE_STORAGE_KEY,
  VISITOR_STORAGE_KEY,
} from '../constants';
import { TRAFFIC_SOURCE } from '../enums';
import { RecordEventService } from '../services';

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/** Click ids an ad network appends: the visit is paid even when the campaign forgot its UTM tags. */
const AD_CLICK_IDS = ['fbclid', 'gclid', 'msclkid'];

/*
 * Where THIS page load says the visitor came from — decided once, at the first event, because a
 * later in-app navigation has no UTM tags and no referrer of its own.
 */
let pageLoadOrigin: TrafficOrigin | null = null;

/**
 * The source of a visit from what the browser knows (rnc-1 R7): `utm_medium` names a paid or a
 * partner campaign; any other tag, an ad click id or a link from another site is organic; nothing
 * at all is `null` — the caller then keeps the session's source, or calls it direct.
 *
 * Only the derived word leaves the browser. The referrer itself is never sent (AM2).
 */
function deriveTrafficOrigin({ host, referrer, search }: ArrivalContext): TrafficOrigin | null {
  const params = new URLSearchParams(search);
  const medium = params.get('utm_medium')?.trim().toLowerCase().slice(0, MEDIUM_MAX_LENGTH) || null;
  const hasCampaignTag = !!params.get('utm_source') || !!medium;
  const hasAdClickId = AD_CLICK_IDS.some((name) => params.has(name));

  if (medium && PAID_MEDIUMS.includes(medium)) return { medium, source: TRAFFIC_SOURCE.PAID };

  if (medium && PARTNER_MEDIUMS.includes(medium)) return { medium, source: TRAFFIC_SOURCE.PARTNER };

  if (hasAdClickId) return { medium, source: TRAFFIC_SOURCE.PAID };

  if (hasCampaignTag) return { medium, source: TRAFFIC_SOURCE.ORGANIC };

  const referrerHost = readHost(referrer);
  const isExternalReferrer = !!referrerHost && referrerHost !== host;

  return isExternalReferrer ? { medium: null, source: TRAFFIC_SOURCE.ORGANIC } : null;
}

function readHost(url: string): string | null {
  if (!url) return null;

  try {
    return new URL(url).host;
  } catch {
    return null;
  }
}

/** Storage can be refused (a private window, a full quota); the visit is then simply anonymous for this page. */
function readStorage(storage: Storage, key: string): string | null {
  try {
    return storage.getItem(key);
  } catch {
    return null;
  }
}

function writeStorage(storage: Storage, key: string, value: string) {
  try {
    storage.setItem(key, value);
  } catch {
    // Nothing to keep it in: the next page derives it again.
  }
}

/** The visitor's first-party id: a UUID v4 kept in localStorage across visits. */
function visitorId(): string {
  const stored = readStorage(localStorage, VISITOR_STORAGE_KEY);

  if (stored && UUID_V4.test(stored)) return stored;

  const created = crypto.randomUUID();

  writeStorage(localStorage, VISITOR_STORAGE_KEY, created);

  return created;
}

function sessionOrigin(): TrafficOrigin {
  if (pageLoadOrigin) return pageLoadOrigin;

  const derived = deriveTrafficOrigin({
    host: window.location.host,
    referrer: document.referrer,
    search: window.location.search,
  });

  if (derived) writeStorage(sessionStorage, TRAFFIC_SOURCE_STORAGE_KEY, JSON.stringify(derived));

  pageLoadOrigin = derived ?? parseStoredOrigin(readStorage(sessionStorage, TRAFFIC_SOURCE_STORAGE_KEY));

  return pageLoadOrigin;
}

function parseStoredOrigin(stored: string | null): TrafficOrigin {
  const DIRECT: TrafficOrigin = { medium: null, source: TRAFFIC_SOURCE.DIRECT };
  const parsed = parseJson(stored);
  const isKnownSource = Object.values(TRAFFIC_SOURCE).includes(parsed?.source as TRAFFIC_SOURCE);

  return isKnownSource ? { medium: parsed?.medium ?? null, source: parsed?.source as TRAFFIC_SOURCE } : DIRECT;
}

function parseJson(stored: string | null): Partial<TrafficOrigin> | null {
  if (!stored) return null;

  try {
    return JSON.parse(stored) as Partial<TrafficOrigin>;
  } catch {
    return null;
  }
}

/** Forgets this page load's source — what a spec does between two simulated arrivals. */
function resetTrafficOrigin() {
  pageLoadOrigin = null;
}

/**
 * Reports the visitor's steps to the funnel (rnc-1 R7, R24): page views, CTA clicks and, from F3,
 * the diagnostic steps. Fire and forget — a lost event never breaks the page.
 */
function useTracking() {
  // EVENTS
  function track(type: TRACKING_EVENT_TYPE, sector: string | null = null): Promise<boolean> {
    const { medium, source } = sessionOrigin();
    const PAYLOAD = { medium, path: window.location.pathname, sector, source, type, visitor: visitorId() };

    return RecordEventService(PAYLOAD);
  }

  return { track };
}

export { deriveTrafficOrigin, resetTrafficOrigin, useTracking };
