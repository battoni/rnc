import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import ABrandPattern from './ABrandPattern.vue';

describe('ABrandPattern', () => {
  it('renders an svg with a pattern definition', () => {
    const wrapper = mountWithPlugins(ABrandPattern);
    expect(wrapper.find('svg').exists()).toBe(true);
    expect(wrapper.find('pattern').exists()).toBe(true);
  });

  it('is decorative (aria-hidden)', () => {
    expect(mountWithPlugins(ABrandPattern).find('svg').attributes('aria-hidden')).toBe('true');
  });
});
