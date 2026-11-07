import { flushPromises } from '@vue/test-utils';
import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ALogo from '@/components/atoms/ALogo/ALogo.vue';
import { mountWithPlugins } from '@/test/mount';
import { server } from '@/test/msw/server';
import { FIRM_FIXTURE } from '../../modules/Site/test/fixtures';
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
  server.use(http.get('http://localhost/public/firm', () => HttpResponse.json({ data: FIRM_FIXTURE })));
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
});
