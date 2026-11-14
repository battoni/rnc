import { flushPromises } from '@vue/test-utils';
import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ALogo from '@/components/atoms/ALogo/ALogo.vue';
import { mountWithPlugins } from '@/test/mount';
import { server } from '@/test/msw/server';
import { FIRM_FIXTURE } from '../../modules/Site/test/fixtures';
import ThePublicLayout from './ThePublicLayout.vue';

/*
 * rnc-4 R27, A43: a firm's uploaded logo replaces the words in the header and the footer, asked on the
 * page's own origin (`/storage` proxied per host). Its own file: the firm is read once per page load
 * and kept, so a case after a firm without a logo would read the kept answer.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRouter: () => ({ push: vi.fn() }),
}));

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

beforeEach(() => {
  sessionStorage.clear();
  server.use(
    http.get('http://localhost/public/firm', () =>
      HttpResponse.json({ data: { ...FIRM_FIXTURE, logo_url: 'http://localhost:8000/storage/firms/1/logo.png' } })
    )
  );
});

describe('ThePublicLayout — a firm with an uploaded logo', () => {
  it('shows the logo in the header and the footer, named by the firm', async () => {
    const wrapper = mountWithPlugins(ThePublicLayout, {
      slots: { default: '<p>Conteúdo</p>' },
      global: { components: { ALogo }, stubs: { RouterLink: RouterLinkStub } },
    });

    await flushPromises();

    expect(wrapper.find('header img[data-testid="logo-image"]').attributes('src')).toBe('/storage/firms/1/logo.png');
    expect(wrapper.find('[data-testid="footer-brand"] img').attributes('alt')).toBe(FIRM_FIXTURE.logo_text);
  });
});
