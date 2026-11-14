import { http, HttpResponse } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { BRAND_PREVIEW_STORAGE_KEY, BRAND_STORAGE_KEY } from '../constants';
import { GetSectorPagesService } from '../services';
import { FIRM_FIXTURE, PRIME_FIRM_FIXTURE } from '../test/fixtures';

/*
 * R2 (rnc-3 F2, AM21): the tab keeps the sub-brand of `?marca=` in `sessionStorage` and sends it as
 * `X-Brand` on every public call; `?marca=franco` drops it, and so does any 404 `brands.notFound` —
 * the call is then asked again as the main brand, so a stale tab lands on Franco instead of an error.
 */
const seen: (string | null)[] = [];

async function freshFirm() {
  vi.resetModules();

  const { usePublicFirm } = await import('./usePublicFirm');

  return usePublicFirm();
}

beforeEach(() => {
  seen.length = 0;
  sessionStorage.clear();
  server.use(
    http.get('http://localhost/public/firm', ({ request }) => {
      const brand = request.headers.get('X-Brand');

      seen.push(brand);

      if (brand === 'prime') return HttpResponse.json({ data: PRIME_FIRM_FIXTURE });

      if (brand) return HttpResponse.json({ data: null, error: 'brands.notFound', status_code: 404 }, { status: 404 });

      return HttpResponse.json({ data: FIRM_FIXTURE });
    }),
    http.get('http://localhost/public/sectors', ({ request }) => {
      seen.push(request.headers.get('X-Brand'));

      return HttpResponse.json({ data: [] });
    })
  );
});

afterEach(() => {
  window.history.replaceState({}, '', '/');
});

describe('usePublicFirm under a sub-brand', () => {
  it('keeps ?marca= for the tab and sends it as X-Brand on every public call', async () => {
    window.history.replaceState({}, '', '/?marca=prime');

    const { firm, load } = await freshFirm();

    await load();

    expect(seen).toEqual(['prime']);
    expect(firm.value?.name).toBe('Franco Prime Contabilidade');
    expect(sessionStorage.getItem(BRAND_STORAGE_KEY)).toBe('prime');

    // The next page of the visit has no `?marca=` in its address: the tab still knows.
    window.history.replaceState({}, '', '/diagnostico');
    await GetSectorPagesService();

    expect(seen).toEqual(['prime', 'prime']);
  });

  it('sends no header on the main brand', async () => {
    const { firm, load } = await freshFirm();

    await load();

    expect(seen).toEqual([null]);
    expect(firm.value?.name).toBe('Franco Contabilidade');
  });

  it('drops the brand on ?marca=franco', async () => {
    sessionStorage.setItem(BRAND_STORAGE_KEY, 'prime');
    sessionStorage.setItem(BRAND_PREVIEW_STORAGE_KEY, '1');
    window.history.replaceState({}, '', '/?marca=franco');

    const { firm, load } = await freshFirm();

    await load();

    expect(seen).toEqual([null]);
    expect(firm.value?.name).toBe('Franco Contabilidade');
    expect(sessionStorage.getItem(BRAND_STORAGE_KEY)).toBeNull();
    expect(sessionStorage.getItem(BRAND_PREVIEW_STORAGE_KEY)).toBeNull();
  });

  it('never sends a slug the API could not know', async () => {
    window.history.replaceState({}, '', '/?marca=%3Cscript%3E');

    const { load } = await freshFirm();

    await load();

    expect(seen).toEqual([null]);
    expect(sessionStorage.getItem(BRAND_STORAGE_KEY)).toBeNull();
  });

  it('drops a brand the API refuses (404 brands.notFound) and asks again as the main brand (AM21)', async () => {
    window.history.replaceState({}, '', '/?marca=velha&previa=1');

    const { firm, load } = await freshFirm();

    await load();

    expect(seen).toEqual(['velha', null]);
    expect(firm.value?.name).toBe('Franco Contabilidade');
    expect(sessionStorage.getItem(BRAND_STORAGE_KEY)).toBeNull();
    expect(sessionStorage.getItem(BRAND_PREVIEW_STORAGE_KEY)).toBeNull();
    // Out of the address bar too, or the next read of it would store the stale slug again.
    expect(window.location.search).toBe('');
  });

  it('keeps the brand on any other 404', async () => {
    sessionStorage.setItem(BRAND_STORAGE_KEY, 'prime');
    server.use(
      http.get('http://localhost/public/sectors', () =>
        HttpResponse.json({ data: null, error: 'content.sectorPage.notFound', status_code: 404 }, { status: 404 })
      )
    );

    await expect(GetSectorPagesService()).rejects.toBeDefined();

    expect(sessionStorage.getItem(BRAND_STORAGE_KEY)).toBe('prime');
  });

  it('asks again when the brand changed since the firm was read', async () => {
    const { firm, load } = await freshFirm();

    await load();
    window.history.replaceState({}, '', '/?marca=prime');
    await load();

    expect(seen).toEqual([null, 'prime']);
    expect(firm.value?.name).toBe('Franco Prime Contabilidade');
  });
});

/*
 * rnc-4 R3, A6: an unknown or reserved subdomain is a 404 `firms.notFound` on the boot read, and the
 * shell swaps every path for "Escritório não encontrado". Any other failure is not that answer.
 */
describe('usePublicFirm on a host that names no firm', () => {
  function answerFirmWith(status: number, error: string) {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    server.use(
      http.get('http://localhost/public/firm', () =>
        HttpResponse.json({ data: null, error, status_code: status }, { status })
      )
    );
  }

  it('says the firm is missing on 404 firms.notFound', async () => {
    answerFirmWith(404, 'firms.notFound');

    const { firm, isFirmMissing, load } = await freshFirm();

    await load();

    expect(isFirmMissing.value).toBe(true);
    expect(firm.value).toBeNull();
  });

  it('does not say so on any other failure', async () => {
    answerFirmWith(500, 'errors.unexpected');

    const { isFirmMissing, load } = await freshFirm();

    await load();

    expect(isFirmMissing.value).toBe(false);
  });

  it('does not say so when the firm answers', async () => {
    const { isFirmMissing, load } = await freshFirm();

    await load();

    expect(isFirmMissing.value).toBe(false);
  });
});
