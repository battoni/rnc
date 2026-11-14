import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { BRAND_STORAGE_KEY } from './constants';

/*
 * Chrome QA 4.11/4.12 (rnc-3 R2, rnc-4 W27): the tab's sub-brand rides on EVERY public call, and the
 * main brand's words — `?marca=principal`, or the host firm's own slug — drop it for every one of them:
 * a diagnostic started after `?marca=prime` then `?marca=principal` is the main brand's lead.
 */
const { hostSlug } = vi.hoisted(() => ({ hostSlug: { value: null as string | null } }));

vi.mock('@Helpers', async (importActual) => ({
  ...(await importActual<Record<string, unknown>>()),
  currentFirmSlug: () => hostSlug.value,
}));

const sent: (string | null)[] = [];

async function freshPublicBrand() {
  vi.resetModules();

  return import('./publicBrand');
}

function startDiagnostic(publicApi: Awaited<ReturnType<typeof freshPublicBrand>>['publicApi']) {
  return publicApi({ endpoint: 'public/diagnostics', method: 'post', payload: { path: 'quiz' } });
}

beforeEach(() => {
  sent.length = 0;
  hostSlug.value = null;
  sessionStorage.clear();
  server.use(
    http.post('http://localhost/public/diagnostics', ({ request }) => {
      sent.push(request.headers.get('X-Brand'));

      return HttpResponse.json({ data: { token: 'tok' } }, { status: 201 });
    })
  );
});

describe('publicBrand', () => {
  it('starts the diagnostic under the main brand after ?marca=prime then ?marca=principal', async () => {
    window.history.replaceState({}, '', '/diagnostico?marca=prime');
    await startDiagnostic((await freshPublicBrand()).publicApi);

    window.history.replaceState({}, '', '/?marca=principal');
    const { brandOfTab, currentBrand, publicApi } = await freshPublicBrand();

    expect(currentBrand()).toBeNull();

    window.history.replaceState({}, '', '/diagnostico');
    await startDiagnostic(publicApi);

    expect(sent).toEqual(['prime', null]);
    expect(brandOfTab.value).toBeNull();
    expect(sessionStorage.getItem(BRAND_STORAGE_KEY)).toBeNull();
  });

  it("drops the sub-brand on the host firm's own slug too (W27)", async () => {
    hostSlug.value = 'franco';
    sessionStorage.setItem(BRAND_STORAGE_KEY, 'prime');
    window.history.replaceState({}, '', '/?marca=franco');

    const { currentBrand, publicApi } = await freshPublicBrand();

    expect(currentBrand()).toBeNull();

    window.history.replaceState({}, '', '/diagnostico');
    await startDiagnostic(publicApi);

    expect(sent).toEqual([null]);
  });
});
