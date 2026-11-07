import { describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import NotFoundView from './NotFoundView.view.vue';
import NotFoundRoutes from './routes';

const { push } = vi.hoisted(() => ({
  push: vi.fn(),
}));

vi.mock('vue-router', async (importOriginal) => {
  const ACTUAL = (await importOriginal()) as Record<string, unknown>;

  return { ...ACTUAL, useRouter: () => ({ push }) };
});

vi.mock('@Helpers', async (importOriginal) => {
  const ACTUAL = (await importOriginal()) as Record<string, unknown>;

  return { ...ACTUAL, getI18nRouteName: (name: string) => name };
});

const STUBS = {
  ALogo: { template: '<div class="logo-stub" />' },
  Button: {
    name: 'Button',
    props: ['icon', 'label', 'severity'],
    template: '<button @click="$emit(\'click\')">{{ label }}</button>',
  },
  TheCenteredLayout: { template: '<div><slot /></div>' },
};

function mountNotFound() {
  return mountWithPlugins(NotFoundView, { global: { stubs: STUBS } });
}

describe('NotFoundView', () => {
  // The address used to render nothing at all: no message, no way back, and no way to tell a typo
  // from an outage.
  it('says the address matches nothing, rather than showing an empty page', () => {
    const wrapper = mountNotFound();

    expect(wrapper.find('[data-testid="not-found"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('This page does not exist');
  });

  it('offers the way back by route name, not a path string', async () => {
    const wrapper = mountNotFound();

    await wrapper.find('button').trigger('click');

    expect(push).toHaveBeenCalledWith({ name: 'home' });
  });
});

describe('NotFoundRoutes', () => {
  it('matches anything left over', () => {
    expect(NotFoundRoutes[0]?.path).toBe('/:pathMatch(.*)*');
  });

  it('is open to everyone, rather than public (guest-only) or private', () => {
    expect(NotFoundRoutes[0]?.meta).toEqual({ isOpen: true });
  });
});
