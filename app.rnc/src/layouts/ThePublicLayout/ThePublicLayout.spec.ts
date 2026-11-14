import { flushPromises } from '@vue/test-utils';
import { http, HttpResponse } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ALogo from '@/components/atoms/ALogo/ALogo.vue';
import { mountWithPlugins } from '@/test/mount';
import { server } from '@/test/msw/server';
import { EXPRESS_FIRM_FIXTURE, FIRM_FIXTURE } from '../../modules/Site/test/fixtures';
import ThePublicLayout from './ThePublicLayout.vue';

const push = vi.fn();
const sendBeacon = vi.fn<(url: string, body: Blob) => boolean>(() => true);

vi.mock('vue-router', async (importOriginal) => ({ ...(await importOriginal<object>()), useRouter: () => ({ push }) }));

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

async function mountLayout() {
  const wrapper = mountWithPlugins(ThePublicLayout, {
    slots: { default: '<p class="page-content">Conteúdo</p>' },
    global: { components: { ALogo }, stubs: { RouterLink: RouterLinkStub } },
  });

  await flushPromises();

  return wrapper;
}

beforeEach(() => {
  push.mockClear();
  sendBeacon.mockClear();
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  sessionStorage.clear();
  server.use(
    http.get('http://localhost/public/firm', ({ request }) =>
      HttpResponse.json({ data: request.headers.get('X-Brand') === 'express' ? EXPRESS_FIRM_FIXTURE : FIRM_FIXTURE })
    )
  );
});

afterEach(() => {
  window.history.replaceState({}, '', '/');
  document.documentElement.removeAttribute('style');
  document.documentElement.removeAttribute('data-brand');
});

describe('ThePublicLayout', () => {
  it('names Franco Contabilidade in the header', async () => {
    const header = (await mountLayout()).find('header');

    expect(header.text()).toContain('Franco Contabilidade');
  });

  it('renders the page between the header and the footer', async () => {
    expect((await mountLayout()).find('main .page-content').text()).toBe('Conteúdo');
  });

  it("carries the firm's public contact from the API in the footer", async () => {
    const footer = (await mountLayout()).find('footer');

    expect(footer.text()).toContain('Av. Corifeu de Azevedo Marques, 2333');
    expect(footer.text()).toContain('11 3732-5500');
    expect(footer.find('a[href="tel:+551137325500"]').exists()).toBe(true);
    expect(footer.find('a[href="mailto:saf@franco-rnc.com.br"]').exists()).toBe(true);
  });

  // rnc-4 F5a (W31): the credentials line is the firm's own field, edited in its settings (a firm
  // without one: `ThePublicLayout.noCredentials.spec.ts`, since the firm is read once per page load).
  it("writes the firm's own credentials line in the footer", async () => {
    const footer = (await mountLayout()).find('footer');

    expect(footer.find('[data-testid="footer-credentials"]').text()).toContain(
      'ISO 9001:2015 · PQEC Sescon-SP · Rede Nacional de Contabilidade'
    );
  });

  it('writes the business hours from the API, a run of days as a range', async () => {
    const footer = (await mountLayout()).find('footer');

    expect(footer.text()).toContain('Monday to Thursday, 8h to 18h');
    expect(footer.text()).toContain('Friday, 8h to 17h');
  });

  it('sends the header CTA to the diagnostic and counts the click', async () => {
    const wrapper = await mountLayout();

    await wrapper.find('[data-testid="header-cta"]').trigger('click');

    expect(push).toHaveBeenCalledWith({ name: 'site-diagnostic' });
    expect(sendBeacon).toHaveBeenCalledTimes(1);
  });

  it('never names the partner network or its founder', async () => {
    const text = (await mountLayout()).text();

    expect(text).not.toContain('Willy');
    expect(text).not.toContain('APW');
  });

  it('paints nothing over the theme on the main brand', async () => {
    await mountLayout();

    expect(document.documentElement.style.getPropertyValue('--color-primary-500')).toBe('');
    expect(document.documentElement.dataset.brand).toBeUndefined();
  });
});

/*
 * A2 (rnc-3 F2, R2, R3; AM3): under `?marca=express` the site is Express's — its colours on the
 * document root (so PrimeVue's tokens and every overlay follow), its name in the header and the
 * copyright, its tagline in the footer — over Franco's own contact. No other firm's name renders.
 * A6: the operator's "Ver como" tab (`previa=1`) wears the preview banner; a visitor's tab does not.
 */
describe('ThePublicLayout under a sub-brand', () => {
  it("sets the brand's colours as CSS properties on the document root", async () => {
    window.history.replaceState({}, '', '/?marca=express');

    await mountLayout();

    const root = document.documentElement;

    expect(root.style.getPropertyValue('--color-primary-500')).toBe('#3b2a86');
    expect(root.style.getPropertyValue('--color-primary-300')).toBe('#ff8a3d');
    expect(root.dataset.brand).toBe('express');
  });

  it("names the brand in the header, the footer's tagline and the copyright, with Franco's contact", async () => {
    window.history.replaceState({}, '', '/?marca=express');

    const wrapper = await mountLayout();
    const header = wrapper.find('header');
    const footer = wrapper.find('footer');

    expect(header.text()).toContain('Franco Express Contabilidade');
    expect(footer.text()).toContain('A contabilidade da Franco no ritmo da pequena empresa');
    expect(footer.text()).toContain(`© ${new Date().getFullYear()} Franco Express Contabilidade`);
    expect(footer.find('a[href="tel:+551137325500"]').exists()).toBe(true);
    expect(footer.find('a[href="mailto:saf@franco-rnc.com.br"]').exists()).toBe(true);
  });

  it("renders no other firm's name: not the main brand's, not another sub-brand's, not the demo's Alvorada", async () => {
    window.history.replaceState({}, '', '/?marca=express');

    const text = (await mountLayout()).text();

    ['Franco Contabilidade', 'Franco Prime', 'Alvorada', 'Willy', 'APW'].forEach((name) =>
      expect(text, name).not.toContain(name)
    );
  });

  // W38, review S1: every point that writes the brand waits on one flag — both marks, the
  // footer's mark and tagline, the copyright — never Franco's words for the one request.
  it("hides every brand point while the brand's answer is on its way, then shows the brand's", async () => {
    let answer: () => void = () => undefined;
    const answered = new Promise<void>((resolve) => (answer = resolve));

    server.use(
      http.get('http://localhost/public/firm', async () => {
        await answered;

        return HttpResponse.json({ data: EXPRESS_FIRM_FIXTURE });
      })
    );
    // A slug this file never loaded: the firm is kept for the tab, as a later page of a visit keeps it.
    window.history.replaceState({}, '', '/?marca=nova');

    const wrapper = mountWithPlugins(ThePublicLayout, {
      slots: { default: '<p>Conteúdo</p>' },
      global: { components: { ALogo }, stubs: { RouterLink: RouterLinkStub } },
    });

    await flushPromises();

    const hidden = () =>
      [
        wrapper.find('header a'),
        wrapper.find('[data-testid="footer-brand"]'),
        wrapper.find('[data-testid="footer-copyright"]'),
      ].map((point) => point.classes().includes('opacity-0'));

    expect(hidden()).toEqual([true, true, true]);

    answer();
    await vi.waitFor(() => expect(hidden()).toEqual([false, false, false]));

    expect(wrapper.find('[data-testid="footer-brand"]').text()).toContain(
      'A contabilidade da Franco no ritmo da pequena empresa'
    );
  });

  it('shows the preview banner only on the operator\'s "Ver como" tab (A6)', async () => {
    window.history.replaceState({}, '', '/?marca=express&previa=1');

    const preview = await mountLayout();
    const banner = preview.find('[data-testid="brand-preview-banner"]');

    expect(banner.exists()).toBe(true);
    expect(banner.text()).toContain('Preview: the site as Franco Express Contabilidade appears to visitors.');

    preview.unmount();
    window.history.replaceState({}, '', '/?marca=express');

    expect((await mountLayout()).find('[data-testid="brand-preview-banner"]').exists()).toBe(false);
  });
});
