import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { BRAND_STORAGE_KEY, FIRM_BRAND_STORAGE_KEY } from './constants';

/*
 * rnc-4 R10, A11: the host firm's brand for both layouts — one `GET public/firm` per page load,
 * without the tab's sub-brand, kept for the tab so the next page starts from it before the API
 * answers. R3: its 404 `firms.notFound` is the "firm not found" page's signal.
 */
const ALVORADA = {
  address: 'Rua das Acácias, 120, Campinas-SP',
  brand: null,
  brand_accent: '#f4b63f',
  brand_primary: '#0f5c4d',
  business_hours: [],
  is_platform: false,
  logo_text: 'Alvorada Contabilidade',
  logo_url: null,
  mailbox: null,
  name: 'Alvorada Contabilidade',
  phone: null,
  phone_href: null,
  platform: { name: 'Hub da Reforma' },
  slug: 'alvorada',
};

const seen: (string | null)[] = [];

async function freshFirmBrand() {
  vi.resetModules();

  const { useFirmBrand } = await import('./useFirmBrand');

  return useFirmBrand();
}

function answerFirmWith(status: number, error: string) {
  vi.spyOn(console, 'error').mockImplementation(() => {});
  server.use(
    http.get('http://localhost/public/firm', () =>
      HttpResponse.json({ data: null, error, status_code: status }, { status })
    )
  );
}

beforeEach(() => {
  seen.length = 0;
  sessionStorage.clear();
  server.use(
    http.get('http://localhost/public/firm', ({ request }) => {
      seen.push(request.headers.get('X-Brand'));

      return HttpResponse.json({ data: ALVORADA });
    })
  );
});

describe('useFirmBrand', () => {
  it("reads the host firm once and names its logo text and the product's name", async () => {
    const { firm, load, logoText, platformName } = await freshFirmBrand();

    await Promise.all([load(), load()]);
    await load();

    expect(seen).toEqual([null]);
    expect(firm.value?.slug).toBe('alvorada');
    expect(logoText.value).toBe('Alvorada Contabilidade');
    expect(platformName.value).toBe('Hub da Reforma');
  });

  it("never sends the tab's sub-brand: the back office is the firm's", async () => {
    sessionStorage.setItem(BRAND_STORAGE_KEY, 'prime');

    const { load } = await freshFirmBrand();

    await load();

    expect(seen).toEqual([null]);
  });

  it("starts the next page from the tab's last answer, before the API replies", async () => {
    const first = await freshFirmBrand();

    await first.load();

    expect(JSON.parse(sessionStorage.getItem(FIRM_BRAND_STORAGE_KEY) ?? 'null')?.slug).toBe('alvorada');

    const second = await freshFirmBrand();

    expect(second.firm.value?.brand_primary).toBe('#0f5c4d');
    expect(second.logoText.value).toBe('Alvorada Contabilidade');
  });

  it('says the firm is missing on 404 firms.notFound', async () => {
    answerFirmWith(404, 'firms.notFound');

    const { firm, isFirmMissing, load } = await freshFirmBrand();

    await load();

    expect(isFirmMissing.value).toBe(true);
    expect(firm.value).toBeNull();
  });

  it('does not say so on any other failure, and asks again on the next load', async () => {
    answerFirmWith(500, 'errors.unexpected');

    const { isFirmMissing, load } = await freshFirmBrand();

    await load();

    expect(isFirmMissing.value).toBe(false);

    server.use(http.get('http://localhost/public/firm', () => HttpResponse.json({ data: ALVORADA })));

    expect((await load())?.slug).toBe('alvorada');
  });
});
