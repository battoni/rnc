import { flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import AFormError from './AFormError.vue';

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/', component: { template: '<div />' } }],
});

const messageStub = {
  template: '<div class="p-message-stub"><slot name="icon" /><slot /></div>',
};

function mount(props: { error?: string | null } = {}) {
  return mountWithPlugins(AFormError, {
    props,
    global: {
      plugins: [router],
      stubs: { Message: messageStub },
    },
  });
}

const UNEXPECTED_PREFIX =
  'An unexpected error occurred. Copy the error bellow and please inform the system administrator: ';

describe('AFormError', () => {
  it('renders nothing when error is null', () => {
    const wrapper = mount({ error: null });
    expect(wrapper.find('.w-full').exists()).toBe(false);
  });

  it('renders nothing when error is not provided', () => {
    const wrapper = mount();
    expect(wrapper.html()).toContain('<!--v-if-->');
  });

  it('renders the error text for a plain (non-unexpected) error', () => {
    const wrapper = mount({ error: 'Validation failed' });
    expect(wrapper.text()).toContain('Validation failed');
  });

  it('splits an unexpected error into intro and detail', () => {
    const wrapper = mount({ error: `${UNEXPECTED_PREFIX}DB connection refused` });
    expect(wrapper.text()).toContain('An unexpected error occurred');
    expect(wrapper.text()).toContain('DB connection refused');
  });

  it('shows the copy icon for unexpected errors', () => {
    const wrapper = mount({ error: `${UNEXPECTED_PREFIX}DB connection refused` });
    expect(wrapper.find('.pi-copy').exists()).toBe(true);
  });

  it('does not show the copy icon for plain errors', () => {
    const wrapper = mount({ error: 'Something went wrong' });
    expect(wrapper.find('.pi-copy').exists()).toBe(false);
  });

  it('offers the copy as a named button a keyboard can reach', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);

    vi.stubGlobal('navigator', { clipboard: { writeText } });

    const wrapper = mount({ error: `${UNEXPECTED_PREFIX}DB connection refused` });
    const copyButton = wrapper.find('button[type="button"]');

    expect(copyButton.exists()).toBe(true);

    expect(copyButton.attributes('aria-label')).toBe('Copy the error');

    expect(copyButton.classes()).toContain('touch-target');

    await copyButton.trigger('click');
    await flushPromises();

    expect(writeText).toHaveBeenCalledWith(expect.stringContaining('DB connection refused'));

    expect(copyButton.attributes('aria-label')).toBe('Error copied');

    vi.unstubAllGlobals();
  });
});
