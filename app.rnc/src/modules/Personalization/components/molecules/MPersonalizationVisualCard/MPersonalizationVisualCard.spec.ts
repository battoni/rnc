import type { Component } from 'vue';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import type { VueWrapper } from '@vue/test-utils';
import { mountWithPlugins } from '@/test/mount';
import { COLOR_SCHEME_STORAGE_KEY, FULLSCREEN_STORAGE_KEY, MENU_BUTTON_PINNED_STORAGE_KEY } from '@Constants';
import { COLOR_SCHEME } from '@Enums';

let MPersonalizationVisualCard: Component;

beforeAll(async () => {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      addEventListener: vi.fn(),
      addListener: vi.fn(),
      dispatchEvent: vi.fn(),
      matches: false,
      media: query,
      onchange: null,
      removeEventListener: vi.fn(),
      removeListener: vi.fn(),
    })),
  });

  MPersonalizationVisualCard = (await import('./MPersonalizationVisualCard.vue')).default;
});

const global = {
  stubs: {
    ToggleSwitch: {
      props: ['modelValue', 'inputId', 'ariaLabel'],
      emits: ['update:modelValue'],
      template: `
        <input
          class="toggle-switch-stub"
          type="checkbox"
          :id="inputId"
          :checked="modelValue"
          @change="$emit('update:modelValue', $event.target.checked)"
        />
      `,
    },
  },
};

describe('MPersonalizationVisualCard', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('renders the visual preferences card with all toggle labels', () => {
    const wrapper = mountWithPlugins(MPersonalizationVisualCard, { global });

    expect(wrapper.find('[data-testid="personalization-visual-card"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('Visual Preferences');
    expect(wrapper.text()).toContain('Dark mode');
    expect(wrapper.text()).toContain('Full screen');
    expect(wrapper.text()).toContain('Pin the menu button');
  });

  // A card is rounded-lg; xl is for a modal or a full-bleed container (lifeshifter-482).
  it('draws the card on the card radius', () => {
    const wrapper = mountWithPlugins(MPersonalizationVisualCard, { global });

    const CARD = wrapper.get('[data-testid="personalization-visual-card"]');

    expect(CARD.classes()).toContain('rounded-lg');
    expect(CARD.classes()).not.toContain('rounded-xl');
  });

  /*
   * A card is a raised surface: its shape comes from relief, not from an outline (celer-06, card
   * chrome — the flat bordered card is retired). And the icon tile is a pale brand tint, which
   * takes the ramp's 950: primary-700 on primary-100 measured 1.83:1, under the 3:1 an icon needs.
   */
  it('draws the card with relief and the icon tile in a readable brand step', () => {
    const wrapper = mountWithPlugins(MPersonalizationVisualCard, { global });

    const CARD = wrapper.get('[data-testid="personalization-visual-card"]');
    const TILE = wrapper.get('[data-testid="personalization-visual-card-icon"]');

    expect(CARD.classes()).toContain('shadow-lift');
    expect(CARD.classes()).not.toContain('border');
    expect(TILE.classes()).toContain('text-primary-950');
    expect(TILE.classes()).not.toContain('text-primary-700');
  });

  it('persists preference changes through the stores', async () => {
    const { useColorSchemeStore } = await import('@Stores');
    const useUiStore = (await import('@Stores')).default;
    const wrapper = mountWithPlugins(MPersonalizationVisualCard, { global });

    const uiStore = useUiStore();
    const colorSchemeStore = useColorSchemeStore();

    // Addressed by the label they belong to, not by position. The list was read as
    // `toggles[0..2]` and adding a fourth preference in the middle silently re-pointed every
    // assertion at its neighbour — the suite went red on the toggle that had not changed.
    await setToggle(wrapper, 'Dark mode', true);
    await setToggle(wrapper, 'Full screen', true);
    await setToggle(wrapper, 'Pin the menu button', true);

    expect(colorSchemeStore.isDark).toBe(true);
    expect(localStorage.getItem(COLOR_SCHEME_STORAGE_KEY)).toBe(COLOR_SCHEME.DARK);
    expect(uiStore.isFullscreen).toBe(true);
    expect(localStorage.getItem(FULLSCREEN_STORAGE_KEY)).toBe('1');
    expect(uiStore.isMenuButtonPinned).toBe(true);
    expect(localStorage.getItem(MENU_BUTTON_PINNED_STORAGE_KEY)).toBe('1');
  });

  it('unpinning the menu button brings a collapsed rail back', async () => {
    const useUiStore = (await import('@Stores')).default;
    const wrapper = mountWithPlugins(MPersonalizationVisualCard, { global });

    const uiStore = useUiStore();

    await setToggle(wrapper, 'Pin the menu button', true);
    uiStore.toggleNavCollapsed();
    expect(uiStore.isNavCollapsed).toBe(true);

    // The button is the only way to reopen the rail, so unpinning it while the rail is folded
    // away would leave the navigation unreachable and the state persisted.
    await setToggle(wrapper, 'Pin the menu button', false);

    expect(uiStore.isMenuButtonPinned).toBe(false);
    expect(uiStore.isNavCollapsed).toBe(false);
  });

  // HELPERS

  /** The toggle whose `<label>` carries this text — position is not identity. */
  async function setToggle(wrapper: VueWrapper, label: string, value: boolean) {
    const row = wrapper.findAll('label').find((candidate) => candidate.text().includes(label));

    if (!row) throw new Error(`No preference row labelled "${label}"`);

    const toggle = wrapper.find(`#${row.attributes('for')}`);

    await toggle.setValue(value);
  }
});
