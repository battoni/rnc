import { flushPromises } from '@vue/test-utils';
import { http, HttpResponse } from 'msw';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ALogo from '@/components/atoms/ALogo/ALogo.vue';
import { mountWithPlugins } from '@/test/mount';
import { server } from '@/test/msw/server';
import { FIRM_FIXTURE } from '../../modules/Site/test/fixtures';
import ThePublicLayout from './ThePublicLayout.vue';

/*
 * rnc-4 F5a (W31): a firm with no credentials line shows no credentials block — the line is the
 * firm's own field now, not Franco's certifications printed on every site. Its own file: the firm is
 * read once per page load and kept, so a case after a firm WITH a line would read the kept answer.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRouter: () => ({ push: vi.fn() }),
}));

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

beforeEach(() => {
  sessionStorage.clear();
  server.use(
    http.get('http://localhost/public/firm', () => HttpResponse.json({ data: { ...FIRM_FIXTURE, credentials: null } }))
  );
});

describe('ThePublicLayout — a firm without a credentials line', () => {
  it('shows no credentials block', async () => {
    const wrapper = mountWithPlugins(ThePublicLayout, {
      slots: { default: '<p>Conteúdo</p>' },
      global: { components: { ALogo }, stubs: { RouterLink: RouterLinkStub } },
    });

    await flushPromises();

    const footer = wrapper.find('footer');

    expect(footer.text()).toContain('11 3732-5500');
    expect(footer.find('[data-testid="footer-credentials"]').exists()).toBe(false);
    expect(footer.text()).not.toContain('ISO 9001:2015');
  });
});
