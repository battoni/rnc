import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import TheCenteredLayout from './TheCenteredLayout.vue';

describe('TheCenteredLayout', () => {
  it('renders the default slot', () => {
    const wrapper = mountWithPlugins(TheCenteredLayout, {
      slots: { default: '<form class="auth-form">Login</form>' },
    });

    expect(wrapper.find('.auth-form').text()).toBe('Login');
  });

  it('centres a single child on a full-height page', () => {
    const wrapper = mountWithPlugins(TheCenteredLayout);
    const classes = wrapper.classes();

    expect(classes).toContain('grid');
    expect(classes).toContain('place-items-center');
    // `dvh`, not `vh`: on a phone the layout viewport is taller than what the person can
    // see, so a footer sized against `vh` fell below the fold.
    expect(classes).toContain('min-h-dvh');
  });

  it('caps the column so an auth flow never spans the viewport', () => {
    const wrapper = mountWithPlugins(TheCenteredLayout);

    expect(wrapper.classes()).toContain('max-w-[360px]');
  });

  it('renders the shell with no content', () => {
    const wrapper = mountWithPlugins(TheCenteredLayout);

    expect(wrapper.text()).toBe('');
  });
});
