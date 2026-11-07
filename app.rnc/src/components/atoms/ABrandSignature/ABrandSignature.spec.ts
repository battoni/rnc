import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import ABrandMark from '../ABrandMark/ABrandMark.vue';
import ABrandSignature from './ABrandSignature.vue';

const global = { components: { ABrandMark } };

describe('ABrandSignature', () => {
  it('renders the default lockup', () => {
    const wrapper = mountWithPlugins(ABrandSignature, { global });

    expect(wrapper.text()).toContain('engineered by');
    expect(wrapper.text()).toContain('battoni.dev');
  });

  it('accepts a custom label and wordmark', () => {
    const wrapper = mountWithPlugins(ABrandSignature, {
      global,
      props: { label: 'a product by', wordmark: 'acme.io' },
    });

    expect(wrapper.text()).toContain('a product by');
    expect(wrapper.text()).toContain('acme.io');
  });
});
