import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { FIRM_FIXTURE, PLATFORM_FIRM_FIXTURE } from '../test/fixtures';

/*
 * rnc-4 A32 (R28): the root serves the product's landing and never the diagnostic; a firm host keeps
 * its own landing and its diagnostic. The choice is the host firm's `is_platform` (`GET public/firm`,
 * the boot read), so it is asked of a fresh module each time — `useFirmBrand` keeps its answer for the
 * page load.
 */
async function freshProductSite() {
  vi.resetModules();

  return import('./productSite');
}

function answerFirm(firm: unknown, status = 200) {
  server.use(http.get('http://localhost/public/firm', () => HttpResponse.json({ data: firm }, { status })));
}

beforeEach(() => sessionStorage.clear());

describe('the home view of the public site', () => {
  it("is the product's landing on the platform firm", async () => {
    answerFirm(PLATFORM_FIRM_FIXTURE);
    const { loadHomeView } = await freshProductSite();
    const product = await import('../views/ProductLanding/ProductLandingView.view.vue');

    expect((await loadHomeView()).default).toBe(product.default);
  });

  it("is the firm's landing on a firm host", async () => {
    answerFirm(FIRM_FIXTURE);
    const { loadHomeView } = await freshProductSite();
    const landing = await import('../views/Landing/LandingView.view.vue');

    expect((await loadHomeView()).default).toBe(landing.default);
  });

  it("is the firm's landing when the firm could not be read", async () => {
    answerFirm(null, 500);
    const { loadHomeView } = await freshProductSite();
    const landing = await import('../views/Landing/LandingView.view.vue');

    expect((await loadHomeView()).default).toBe(landing.default);
  });
});

describe('a page only a firm serves (the diagnostic, a sector page)', () => {
  it("sends the platform firm's visitor to the product's landing", async () => {
    answerFirm(PLATFORM_FIRM_FIXTURE);
    const { firmSiteOnly } = await freshProductSite();

    expect(await firmSiteOnly()).toEqual({ name: 'site-home' });
  });

  it('opens on a firm host', async () => {
    answerFirm(FIRM_FIXTURE);
    const { firmSiteOnly } = await freshProductSite();

    expect(await firmSiteOnly()).toBe(true);
  });
});
