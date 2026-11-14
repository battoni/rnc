import { flushPromises } from '@vue/test-utils';
import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ALogo from '@/components/atoms/ALogo/ALogo.vue';
import { mountWithPlugins } from '@/test/mount';
import { server } from '@/test/msw/server';

/*
 * rnc-4 R15: the screens that show the mark on their own — sign-in, terms, not found, the repricing
 * letter — draw the HOST firm's logo text, from its answer, never a name in the build.
 */
const ALVORADA = {
  address: null,
  brand: null,
  brand_accent: '#f4b63f',
  brand_primary: '#0f5c4d',
  business_hours: [],
  logo_text: 'Alvorada Contabilidade',
  mailbox: null,
  name: 'Alvorada Contabilidade',
  phone: null,
  phone_href: null,
  platform: { name: 'Hub da Reforma' },
  slug: 'alvorada',
};

async function mountLogo(attrs: Record<string, unknown> = {}) {
  vi.resetModules();

  const { useFirmBrand } = await import('@Composables');
  const { default: MFirmLogo } = await import('./MFirmLogo.vue');

  await useFirmBrand().load();

  const wrapper = mountWithPlugins(MFirmLogo, { attrs, global: { components: { ALogo } } });

  await flushPromises();

  return wrapper;
}

beforeEach(() => {
  sessionStorage.clear();
  server.use(http.get('http://localhost/public/firm', () => HttpResponse.json({ data: ALVORADA })));
});

describe('MFirmLogo', () => {
  it("writes the host firm's logo text", async () => {
    expect((await mountLogo()).text()).toBe('Alvorada Contabilidade');
  });

  it('passes the variant and the tone to the mark', async () => {
    const wrapper = await mountLogo({ tone: 'inherit', variant: 'min' });

    expect(wrapper.find('[role="img"]').attributes('aria-label')).toBe('Alvorada Contabilidade');
    expect(wrapper.classes()).not.toContain('text-brand-word');
  });

  it('draws nothing while no firm is known', async () => {
    server.use(http.get('http://localhost/public/firm', () => HttpResponse.json({}, { status: 404 })));

    expect((await mountLogo()).text()).toBe('');
  });

  // rnc-4 R27, A43: the host firm's uploaded logo, asked on the page's own origin (`/storage` proxied).
  it("shows the host firm's uploaded logo", async () => {
    server.use(
      http.get('http://localhost/public/firm', () =>
        HttpResponse.json({ data: { ...ALVORADA, logo_url: 'http://localhost:8000/storage/firms/3/logo.png' } })
      )
    );

    const image = (await mountLogo()).find('img[data-testid="logo-image"]');

    expect(image.attributes('src')).toBe('/storage/firms/3/logo.png');
  });
});
