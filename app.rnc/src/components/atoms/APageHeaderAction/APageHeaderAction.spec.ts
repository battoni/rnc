import type { Component } from 'vue';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

let APageHeaderAction: Component;

beforeAll(async () => {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      addEventListener: vi.fn(),
      addListener: vi.fn(),
      dispatchEvent: vi.fn(),
      matches: query === '(min-width: 768px)',
      media: query,
      onchange: null,
      removeEventListener: vi.fn(),
      removeListener: vi.fn(),
    })),
  });

  APageHeaderAction = (await import('./APageHeaderAction.vue')).default;
});

const global = {
  stubs: {
    Button: {
      inheritAttrs: false,
      props: ['icon', 'label', 'class'],
      template: `
        <button
          class="button-stub"
          type="button"
          :aria-label="$attrs['aria-label']"
          :class="$props.class"
          @click="$emit('click', $event)"
        >
          <span v-if="icon" :class="icon" />
          <span v-if="label">{{ label }}</span>
        </button>
      `,
    },
  },
};

describe('APageHeaderAction', () => {
  it('renders icon and label from md breakpoint up', () => {
    const wrapper = mountWithPlugins(APageHeaderAction, {
      global,
      props: {
        icon: 'pi pi-plus',
        label: 'New profile',
      },
    });

    expect(wrapper.find('.pi-plus').exists()).toBe(true);
    expect(wrapper.text()).toContain('New profile');
    expect(wrapper.find('button').attributes('aria-label')).toBe('New profile');
  });

  it('hides visible label below md while preserving accessible name', async () => {
    vi.mocked(window.matchMedia).mockImplementation((query: string) => ({
      addEventListener: vi.fn(),
      addListener: vi.fn(),
      dispatchEvent: vi.fn(),
      matches: false,
      media: query,
      onchange: null,
      removeEventListener: vi.fn(),
      removeListener: vi.fn(),
    }));

    const wrapper = mountWithPlugins(APageHeaderAction, {
      global,
      props: {
        icon: 'pi pi-plus',
        label: 'New profile',
      },
    });

    await wrapper.vm.$nextTick();

    expect(wrapper.text()).not.toContain('New profile');
    expect(wrapper.find('.pi-plus').exists()).toBe(true);
    expect(wrapper.find('button').attributes('aria-label')).toBe('New profile');
    // 44px, the touch floor (celer-06): an icon-only default button is not grown by touch-target.css.
    expect(wrapper.find('button').classes()).toContain('max-md:size-11');
  });

  // A duration is a motion token, so `prefers-reduced-motion` can shorten it (styles/tailwind.css).
  it('takes its transition duration from a motion token, never a literal', () => {
    const wrapper = mountWithPlugins(APageHeaderAction, {
      global,
      props: {
        icon: 'pi pi-plus',
        label: 'New profile',
      },
    });

    const classes = wrapper.find('button').classes();

    expect(classes).toContain('motion-calm');

    expect(classes.filter((name) => /^duration-\d+$/.test(name))).toEqual([]);
  });

  it('forwards click events', async () => {
    const onClick = vi.fn();
    const wrapper = mountWithPlugins(APageHeaderAction, {
      attrs: { onClick },
      global,
      props: {
        icon: 'pi pi-plus',
        label: 'New profile',
      },
    });

    await wrapper.find('button').trigger('click');

    expect(onClick).toHaveBeenCalledOnce();
  });
});
