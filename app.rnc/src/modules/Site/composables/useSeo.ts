import { toValue, watchEffect } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import type { SeoMeta } from '../interfaces';

const OPEN_GRAPH_LOCALE = 'pt_BR';
const OPEN_GRAPH_TYPE = 'website';

/**
 * A public page's title, description and Open Graph tags (rnc-1 R8), kept in step with the page.
 *
 * The SPA has no server render (D4), so these are written into `<head>` at runtime: a crawler that
 * runs JavaScript and a link preview that reads the live DOM both see them; one that does not sees
 * the shell (Site `RULES.md`, Known Gaps). `null` leaves the head as it is — a page still loading.
 * The firm's name, once known, ends the document title; the Open Graph title is the page's own.
 */
function useSeo(meta: MaybeRefOrGetter<(SeoMeta & { siteName?: string | null }) | null>) {
  watchEffect(onMetaChange);

  // HELPERS
  function setMetaTag(attribute: 'name' | 'property', key: string, content: string) {
    const existing = document.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`);
    const element = existing ?? document.head.appendChild(document.createElement('meta'));

    element.setAttribute(attribute, key);
    element.setAttribute('content', content);
  }

  // EVENTS
  function onMetaChange() {
    const current = toValue(meta);

    if (!current) return;

    document.title = current.siteName ? `${current.title} · ${current.siteName}` : current.title;

    setMetaTag('name', 'description', current.description);
    setMetaTag('property', 'og:title', current.title);
    setMetaTag('property', 'og:description', current.description);
    setMetaTag('property', 'og:type', OPEN_GRAPH_TYPE);
    setMetaTag('property', 'og:locale', OPEN_GRAPH_LOCALE);
    setMetaTag('property', 'og:url', window.location.href);

    if (current.siteName) setMetaTag('property', 'og:site_name', current.siteName);
  }
}

export { useSeo };
