import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import AEmptyState from './AEmptyState.vue';

describe('AEmptyState', () => {
  it('renders the title and description', () => {
    const wrapper = mountWithPlugins(AEmptyState, {
      props: { title: 'No users yet', description: 'Add one to start.' },
    });

    expect(wrapper.text()).toContain('No users yet');
    expect(wrapper.text()).toContain('Add one to start.');
  });

  it('shows the default code-symbol glyph', () => {
    expect(mountWithPlugins(AEmptyState).text()).toContain('>_');
  });

  it('renders the icon it is given', () => {
    const wrapper = mountWithPlugins(AEmptyState, { props: { icon: 'pi pi-users' } });

    expect(wrapper.find('i.pi-users').exists()).toBe(true);
  });

  /*
   * The tile falls back to a neutral icon, never to the brand mark.
   *
   * `<ABrandMark>` was the default, and its accessible name is a person's — so every icon-less
   * empty state in every product generated from this bootstrap announced that name out loud.
   * There is a structural half too: this component is MERGE in the sync manifest and travels
   * everywhere, while ABrandMark is LOCAL and never leaves, so the fallback was a shared
   * component reaching into the brand.
   */
  it('falls back to a neutral icon, and never to the brand mark', () => {
    const wrapper = mountWithPlugins(AEmptyState);

    expect(wrapper.find('i.pi-inbox').exists()).toBe(true);
    expect(wrapper.find('svg').exists()).toBe(false);
    expect(wrapper.html()).not.toContain('ABrandMark');
  });
});
