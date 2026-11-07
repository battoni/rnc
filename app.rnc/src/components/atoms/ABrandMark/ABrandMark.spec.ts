import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import ABrandMark from './ABrandMark.vue';

describe('ABrandMark', () => {
  it('renders an svg', () => {
    expect(mountWithPlugins(ABrandMark).find('svg').exists()).toBe(true);
  });

  it('fills the mark with currentColor', () => {
    expect(mountWithPlugins(ABrandMark).find('path').attributes('fill')).toBe('currentColor');
  });

  it('exposes an accessible label', () => {
    const wrapper = mountWithPlugins(ABrandMark, { props: { title: 'Acme' } });
    expect(wrapper.find('svg').attributes('aria-label')).toBe('Acme');
  });
});
