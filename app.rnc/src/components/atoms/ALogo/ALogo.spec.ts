import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import ALogo from './ALogo.vue';

/*
 * The firm's mark is a text logo (rnc-1): the firm's name as the wordmark in `full`, its initial tile
 * in `min`. No image to load, so it can never be missing, and its colour comes from tokens.
 */
describe('ALogo', () => {
  it('writes the firm name in full', () => {
    const wrapper = mountWithPlugins(ALogo, { props: { name: 'Alvorada Contabilidade' } });

    expect(wrapper.text()).toBe('Alvorada Contabilidade');
  });

  // rnc-4 R15: the build carries no firm's name — with none given, the mark draws nothing.
  it('draws nothing without a name', () => {
    expect(mountWithPlugins(ALogo).text()).toBe('');
    expect(
      mountWithPlugins(ALogo, { props: { variant: 'min' } })
        .find('[role="img"]')
        .exists()
    ).toBe(false);
  });

  it('reads the min tile as the product name, not as a letter', () => {
    const tile = mountWithPlugins(ALogo, { props: { name: 'Alvorada Contabilidade', variant: 'min' } }).find(
      '[role="img"]'
    );

    expect(tile.attributes('aria-label')).toBe('Alvorada Contabilidade');
    expect(tile.text()).toBe('A');
  });

  // `text-brand-word`, never a ramp step: `text-primary-500` was navy on the dark scheme's
  // near-black header (1.6:1), and which step reads on a dark ground is the theme's call.
  it('paints the brand as a word, or inherits the parent colour', () => {
    expect(mountWithPlugins(ALogo, { props: { name: 'Alfa' } }).classes()).toContain('text-brand-word');
    expect(mountWithPlugins(ALogo, { props: { name: 'Alfa' } }).classes()).not.toContain('text-primary-500');
    expect(mountWithPlugins(ALogo, { props: { name: 'Alfa', tone: 'inherit' } }).classes()).not.toContain(
      'text-brand-word'
    );
  });

  it('carries no image', () => {
    expect(
      mountWithPlugins(ALogo, { props: { name: 'Alfa' } })
        .find('img')
        .exists()
    ).toBe(false);
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

  // rnc-3 review S5: a one-word brand has no lighter half, so no empty span trails the word.
  it('writes a one-word name without an empty second half', () => {
    const wrapper = mountWithPlugins(ALogo, { props: { name: 'Alfa' } });

    expect(wrapper.text()).toBe('Alfa');
    expect(wrapper.find('.font-normal').exists()).toBe(false);
  });

  // rnc-4 R27, A43: a firm's uploaded logo is the mark — a raster image, named by the firm, in both
  // variants; the text mark comes back if the image cannot load.
  describe('with an uploaded logo', () => {
    const LOGO = '/storage/firms/3/logo-abc.png';

    it('shows the image instead of the words, named by the firm', () => {
      const wrapper = mountWithPlugins(ALogo, { props: { name: 'Alvorada Contabilidade', src: LOGO } });
      const image = wrapper.find('img[data-testid="logo-image"]');

      expect(image.attributes('src')).toBe(LOGO);
      expect(image.attributes('alt')).toBe('Alvorada Contabilidade');
      expect(wrapper.text()).toBe('');
    });

    it('shows the image in the min variant too, without the initial tile', () => {
      const wrapper = mountWithPlugins(ALogo, {
        props: { name: 'Alvorada Contabilidade', src: LOGO, variant: 'min' },
      });

      expect(wrapper.find('img[data-testid="logo-image"]').exists()).toBe(true);
      expect(wrapper.text()).toBe('');
    });

    it('falls back to the text mark when the image fails to load', async () => {
      const wrapper = mountWithPlugins(ALogo, { props: { name: 'Alvorada Contabilidade', src: LOGO } });

      await wrapper.find('img').trigger('error');

      expect(wrapper.find('img').exists()).toBe(false);
      expect(wrapper.text()).toBe('Alvorada Contabilidade');
    });

    it('draws nothing without a name, image or not', () => {
      expect(
        mountWithPlugins(ALogo, { props: { src: LOGO } })
          .find('img')
          .exists()
      ).toBe(false);
    });
  });
});
