import { computed } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useFirmBrand } from '@Composables';
import { prefersReducedMotionHelper } from '@Helpers';
import { SITE_ROUTE_NAMES } from '../constants';

/*
 * The root's public site is the product's own (rnc-4 R28): the host firm is the platform firm
 * (`is_platform`, the boot read of `GET public/firm`), so `/` is the landing for accounting firms and
 * the pages only a firm serves — the diagnostic, a sector page — go back to it. A firm host keeps its
 * own landing and its diagnostic. A firm that could not be read is treated as a firm: an outage must
 * not turn a firm's site into the product's.
 *
 * The answer is `useFirmBrand`'s one read per page load, so the route waits on a request the app
 * makes at boot anyway, and a later page of the visit is answered from the tab at once.
 */
function isPlatformFirm(): Promise<boolean> {
  return useFirmBrand()
    .load()
    .then((firm) => Boolean(firm?.is_platform));
}

/** The `/` route's view: the product's landing on the platform firm, the firm's landing anywhere else. */
function loadHomeView() {
  return isPlatformFirm().then((isPlatform) =>
    isPlatform
      ? import('../views/ProductLanding/ProductLandingView.view.vue')
      : import('../views/Landing/LandingView.view.vue')
  );
}

/** `beforeEnter` of a page only a firm serves: on the platform firm it is the product's landing instead. */
function firmSiteOnly(): Promise<RouteLocationRaw | true> {
  return isPlatformFirm().then((isPlatform) => (isPlatform ? { name: SITE_ROUTE_NAMES.home } : true));
}

/** Scrolls to a section of the page smoothly — at once under reduced motion — and keeps the hash. */
function scrollToSection(anchor: string) {
  const SECTION = document.getElementById(anchor);

  if (!SECTION) return;

  SECTION.scrollIntoView({ behavior: prefersReducedMotionHelper() ? 'auto' : 'smooth', block: 'start' });
  window.history.replaceState(window.history.state, '', `#${anchor}`);
}

/** Whether the page is the product's own site — the shell's nav, CTA and footer follow it. */
function useProductSite() {
  const { firm } = useFirmBrand();

  const isProductSite = computed(() => Boolean(firm.value?.is_platform));

  return { isProductSite };
}

export { firmSiteOnly, loadHomeView, scrollToSection, useProductSite };
