import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import FirmNotFoundView from './FirmNotFoundView.view.vue';

/*
 * rnc-4 R3, A6: a subdomain the API does not know answers a calm page — what happened, what to
 * check, and a way to the root. A link, not a route push: the root is another host.
 */
const STUBS = {
  ALogo: { template: '<div class="logo-stub" />' },
  Button: {
    props: ['as', 'href', 'label'],
    template: '<component :is="as || \'button\'" :href="href">{{ label }}</component>',
  },
  TheCenteredLayout: { template: '<div><slot /></div>' },
};

function mountFirmNotFound() {
  return mountWithPlugins(FirmNotFoundView, { global: { stubs: STUBS } });
}

describe('FirmNotFoundView', () => {
  it('says the firm was not found and what to check', () => {
    const wrapper = mountFirmNotFound();

    expect(wrapper.find('[data-testid="firm-not-found"]').exists()).toBe(true);
    expect(wrapper.find('h1').text()).toBe('Firm not found');
    expect(wrapper.text()).toContain('Check the address or go back to the home page');
  });

  it('links to the root on the same scheme and port', () => {
    const wrapper = mountFirmNotFound();
    const PORT = window.location.port ? `:${window.location.port}` : '';

    expect(wrapper.find('a').attributes('href')).toBe(`${window.location.protocol}//localhost${PORT}/`);
  });
});
