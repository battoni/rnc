import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import ALogo from './ALogo.vue';

describe('ALogo', () => {
  it('renders an img element', () => {
    const wrapper = mountWithPlugins(ALogo);
    expect(wrapper.find('img').exists()).toBe(true);
  });

  it('has descriptive alt text', () => {
    const wrapper = mountWithPlugins(ALogo);
    expect(wrapper.find('img').attributes('alt')).toBeTruthy();
  });

  it('defaults to the full variant', () => {
    const wrapper = mountWithPlugins(ALogo);
    expect(wrapper.props('variant')).toBe('full');
  });

  it('accepts the min variant', () => {
    const wrapper = mountWithPlugins(ALogo, { props: { variant: 'min' } });
    expect(wrapper.props('variant')).toBe('min');
  });

  it('uses a different image source for full vs min variant', () => {
    const wrapperFull = mountWithPlugins(ALogo, { props: { variant: 'full' } });
    const wrapperMin = mountWithPlugins(ALogo, { props: { variant: 'min' } });

    const fullSrc = wrapperFull.find('img').attributes('src');
    const minSrc = wrapperMin.find('img').attributes('src');

    expect(fullSrc).not.toBe(minSrc);
  });
});
