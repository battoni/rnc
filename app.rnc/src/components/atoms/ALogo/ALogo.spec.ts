import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import ALogo from './ALogo.vue';

/*
 * Franco's mark is a text logo (rnc-1): the wordmark "Franco Contabilidade" in `full`, the "F" tile
 * in `min`. No image to load, so it can never be missing, and its colour comes from tokens.
 */
describe('ALogo', () => {
  it('writes the firm name in full', () => {
    const wrapper = mountWithPlugins(ALogo);

    expect(wrapper.text()).toBe('Franco Contabilidade');
  });

  it('reads the min tile as the product name, not as a letter', () => {
    const tile = mountWithPlugins(ALogo, { props: { variant: 'min' } }).find('[role="img"]');

    expect(tile.attributes('aria-label')).toBe('Franco Contabilidade');
    expect(tile.text()).toBe('F');
  });

  it('paints from the brand token, or inherits the parent colour', () => {
    expect(mountWithPlugins(ALogo).classes()).toContain('text-primary-500');
    expect(mountWithPlugins(ALogo, { props: { tone: 'inherit' } }).classes()).not.toContain('text-primary-500');
  });

  it('carries no image', () => {
    expect(mountWithPlugins(ALogo).find('img').exists()).toBe(false);
  });
});
