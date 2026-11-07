import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import { DESKTOP_BREAKPOINT_PX } from '@Constants';
import useUiStore from '@Stores';
import AMenuButton from './AMenuButton.vue';

const MENU_BUTTON = '[data-testid="page-header-menu"]';

function setViewportWidth(width: number) {
  Object.defineProperty(window, 'innerWidth', { configurable: true, value: width, writable: true });
  window.dispatchEvent(new Event('resize'));
}

function mountHeader() {
  const wrapper = mountWithPlugins(AMenuButton);

  // mountWithPlugins installs its own Pinia, so the store must be read after mounting or
  // the assertions inspect a different instance from the one the component holds.
  return { store: useUiStore(), wrapper };
}

describe('AMenuButton', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('opens the drawer below the desktop breakpoint, where the drawer is the only navigation', async () => {
    setViewportWidth(DESKTOP_BREAKPOINT_PX - 200);

    const { store, wrapper } = mountHeader();
    store.isNavCollapsed = true;
    await wrapper.vm.$nextTick();

    await wrapper.find(MENU_BUTTON).trigger('click');

    expect(store.isSidenavOpen).toBe(true);
    expect(store.isNavCollapsed).toBe(true);
  });

  it('restores the rail on a wide screen instead of asking for a drawer that cannot paint', async () => {
    setViewportWidth(DESKTOP_BREAKPOINT_PX + 400);

    const { store, wrapper } = mountHeader();
    store.isNavCollapsed = true;
    await wrapper.vm.$nextTick();

    await wrapper.find(MENU_BUTTON).trigger('click');

    // ONavbarDrawer carries lg:hidden, so opening it here would dim the page behind a
    // panel that never paints — the bug this branch exists to avoid.
    expect(store.isNavCollapsed).toBe(false);
    expect(store.isSidenavOpen).toBe(false);
  });

  it('folds the rail away on a wide screen when it is showing', async () => {
    setViewportWidth(DESKTOP_BREAKPOINT_PX + 400);

    const { store, wrapper } = mountHeader();

    await wrapper.find(MENU_BUTTON).trigger('click');

    expect(store.isNavCollapsed).toBe(true);
    expect(store.isSidenavOpen).toBe(false);
  });

  it('shows the button at every width once it is pinned', async () => {
    setViewportWidth(DESKTOP_BREAKPOINT_PX + 400);

    const { store, wrapper } = mountHeader();
    store.isMenuButtonPinned = true;
    await wrapper.vm.$nextTick();

    // Unpinned it is `hidden md:flex lg:hidden` — present in the DOM but only painted in
    // the tablet range, which is exactly the state this preference exists to escape.
    expect(wrapper.find(MENU_BUTTON).classes()).toContain('flex');
    expect(wrapper.find(MENU_BUTTON).classes()).not.toContain('lg:hidden');
  });
});
