import type { TrackingEventPayload } from '../interfaces';
import { API_URL } from '@Constants';

const BEACON_CONTENT_TYPE = 'text/plain;charset=UTF-8';

/**
 * Sends one funnel event (rnc-1 R7) and never throws: a lost page view must not break a page.
 *
 * Not through `API`, on purpose. The router aborts every request in flight on each navigation
 * (`useGlobalAbortController`), and a CTA click IS a navigation — its event would be called off
 * before it left. `navigator.sendBeacon` outlives the page that sent it, and its `text/plain` body
 * needs no CORS preflight (`RecordEventRequest` reads it as JSON). It also stays out of the app-busy
 * counter, so a page view never shows the loading bar. `fetch` with `keepalive` is the fallback for
 * a browser without a beacon, or one that refused to queue it.
 */
export default async function recordEvent(payload: TrackingEventPayload): Promise<boolean> {
  const URL = `${API_URL}/public/events`;
  const BODY = JSON.stringify(payload);

  const isQueued =
    typeof navigator.sendBeacon === 'function' &&
    navigator.sendBeacon(URL, new Blob([BODY], { type: BEACON_CONTENT_TYPE }));

  if (isQueued) return true;

  return fetch(URL, {
    body: BODY,
    credentials: 'include',
    headers: { 'Content-Type': BEACON_CONTENT_TYPE },
    keepalive: true,
    method: 'POST',
  })
    .then((response) => response.ok)
    .catch(() => false);
}
