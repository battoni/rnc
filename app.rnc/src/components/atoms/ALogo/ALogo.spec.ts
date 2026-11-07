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

  // `text-brand-word`, never a ramp step: `text-primary-500` was navy on the dark scheme's
  // near-black header (1.6:1), and which step reads on a dark ground is the theme's call.
  it('paints the brand as a word, or inherits the parent colour', () => {
    expect(mountWithPlugins(ALogo).classes()).toContain('text-brand-word');
    expect(mountWithPlugins(ALogo).classes()).not.toContain('text-primary-500');
    expect(mountWithPlugins(ALogo, { props: { tone: 'inherit' } }).classes()).not.toContain('text-brand-word');
  });

  it('carries no image', () => {
    expect(mountWithPlugins(ALogo).find('img').exists()).toBe(false);
  });

  // rnc-3 R3: the public site under a sub-brand writes that brand's name, in the same mark.
  it("writes a sub-brand's name when given one, the first word in the heavier weight", () => {
    const wrapper = mountWithPlugins(ALogo, { props: { name: 'Franco Express Contabilidade' } });

    expect(wrapper.text()).toBe('Franco Express Contabilidade');
    expect(wrapper.find('.font-normal').text()).toBe('Express Contabilidade');

    const tile = mountWithPlugins(ALogo, { props: { name: 'Franco Express Contabilidade', variant: 'min' } }).find(
      '[role="img"]'
    );

    expect(tile.attributes('aria-label')).toBe('Franco Express Contabilidade');
    expect(tile.text()).toBe('F');
  });
});
