import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resetSingleTipState, useSingleTip } from '@Composables';
import {
  FULLSCREEN_STORAGE_KEY,
  MENU_BUTTON_PINNED_STORAGE_KEY,
  NAV_COLLAPSED_STORAGE_KEY,
  NAV_EXPANDED_GROUPS_STORAGE_KEY,
  TUTOR_STORAGE_KEY,
} from '@Constants';
import useUiStore from './ui.store';

describe('ui.store', () => {
  beforeEach(() => {
    localStorage.clear();
    delete document.documentElement.dataset.tutor;
    resetSingleTipState();
    setActivePinia(createPinia());
  });

  it('initialises with isSidenavOpen false', () => {
    const store = useUiStore();
    expect(store.isSidenavOpen).toBe(false);
  });

  it('initialises with isFullscreen false when storage is empty', () => {
    const store = useUiStore();
    expect(store.isFullscreen).toBe(false);
  });

  it('initialises isFullscreen from localStorage', () => {
    localStorage.setItem(FULLSCREEN_STORAGE_KEY, '1');

    const store = useUiStore();
    expect(store.isFullscreen).toBe(true);
  });

  it('initialises with isTutorEnabled true when storage is empty', () => {
    const store = useUiStore();
    expect(store.isTutorEnabled).toBe(true);
  });

  it('initialises isTutorEnabled from localStorage', () => {
    localStorage.setItem(TUTOR_STORAGE_KEY, '0');

    const store = useUiStore();
    expect(store.isTutorEnabled).toBe(false);
  });

  it('toggleSidenav opens the sidenav when it is closed', () => {
    const store = useUiStore();
    store.toggleSidenav();
    expect(store.isSidenavOpen).toBe(true);
  });

  it('toggleSidenav closes the sidenav when it is open', () => {
    const store = useUiStore();
    store.toggleSidenav();
    store.toggleSidenav();
    expect(store.isSidenavOpen).toBe(false);
  });

  it('closeSidenav sets isSidenavOpen to false', () => {
    const store = useUiStore();
    store.toggleSidenav();
    store.closeSidenav();
    expect(store.isSidenavOpen).toBe(false);
  });

  it('closeSidenav is a no-op when already closed', () => {
    const store = useUiStore();
    store.closeSidenav();
    expect(store.isSidenavOpen).toBe(false);
  });

  it('setFullscreen updates isFullscreen and persists to localStorage', () => {
    const store = useUiStore();
    store.setFullscreen(true);
    expect(store.isFullscreen).toBe(true);
    expect(localStorage.getItem(FULLSCREEN_STORAGE_KEY)).toBe('1');

    store.setFullscreen(false);
    expect(store.isFullscreen).toBe(false);
    expect(localStorage.getItem(FULLSCREEN_STORAGE_KEY)).toBe('0');
  });

  it('setTutorEnabled updates isTutorEnabled, persists, and applies data-tutor', () => {
    const store = useUiStore();
    store.setTutorEnabled(false);
    expect(store.isTutorEnabled).toBe(false);
    expect(localStorage.getItem(TUTOR_STORAGE_KEY)).toBe('0');
    expect(document.documentElement.dataset.tutor).toBe('off');

    store.setTutorEnabled(true);
    expect(store.isTutorEnabled).toBe(true);
    expect(localStorage.getItem(TUTOR_STORAGE_KEY)).toBe('1');
    expect(document.documentElement.dataset.tutor).toBe('on');
  });

  it('setTutorEnabled(false) dismisses an open contextual tip', () => {
    const { openTip } = useSingleTip();
    const closeActiveTip = vi.fn();
    openTip(closeActiveTip);

    const store = useUiStore();
    store.setTutorEnabled(false);

    expect(closeActiveTip).toHaveBeenCalledOnce();
  });

  it('init applies data-tutor from stored preference', () => {
    localStorage.setItem(TUTOR_STORAGE_KEY, '0');
    setActivePinia(createPinia());

    const store = useUiStore();
    store.init();
    expect(document.documentElement.dataset.tutor).toBe('off');
  });

  it('exposes appWidth and appHeight getters', () => {
    const store = useUiStore();
    expect(typeof store.appWidth).toBe('number');
    expect(typeof store.appHeight).toBe('number');
  });

  it('starts with the navigation visible when storage is empty', () => {
    const store = useUiStore();
    expect(store.isNavCollapsed).toBe(false);
  });

  it('initialises isNavCollapsed from localStorage', () => {
    localStorage.setItem(NAV_COLLAPSED_STORAGE_KEY, '1');
    localStorage.setItem(MENU_BUTTON_PINNED_STORAGE_KEY, '1');

    const store = useUiStore();
    expect(store.isNavCollapsed).toBe(true);
  });

  /**
   * A collapsed rail is only reopenable from the menu button, and the button only shows at
   * every width when it is pinned. The pair is kept consistent on write, but a stored
   * combination from an older build or another tab could still arrive broken — and it would
   * start the app with no navigation and no way to ask for it.
   */
  it('opens the rail when storage says collapsed but the button is not pinned', () => {
    localStorage.setItem(NAV_COLLAPSED_STORAGE_KEY, '1');
    localStorage.setItem(MENU_BUTTON_PINNED_STORAGE_KEY, '0');

    const store = useUiStore();
    expect(store.isNavCollapsed).toBe(false);
  });

  it('toggleNavCollapsed persists the choice and shuts the drawer with it', () => {
    const store = useUiStore();
    store.isSidenavOpen = true;

    store.toggleNavCollapsed();

    expect(store.isNavCollapsed).toBe(true);
    // The rail and the drawer must never claim the screen at the same time.
    expect(store.isSidenavOpen).toBe(false);
    expect(localStorage.getItem(NAV_COLLAPSED_STORAGE_KEY)).toBe('1');

    store.toggleNavCollapsed();

    expect(store.isNavCollapsed).toBe(false);
    expect(localStorage.getItem(NAV_COLLAPSED_STORAGE_KEY)).toBe('0');
  });

  it('starts with every navigation group collapsed when storage is empty', () => {
    expect(useUiStore().expandedNavGroups).toEqual([]);
  });

  it('initialises expandedNavGroups from localStorage', () => {
    localStorage.setItem(NAV_EXPANDED_GROUPS_STORAGE_KEY, 'navigation.trails');

    const store = useUiStore();

    expect(store.expandedNavGroups).toEqual(['navigation.trails']);
  });

  /** Storage is older than the one-open-group rule, and a value from before it must not boot
   *  the app into a state the rule forbids. */
  it('keeps only the first group when storage names several', () => {
    localStorage.setItem(NAV_EXPANDED_GROUPS_STORAGE_KEY, 'navigation.trails,season');

    const store = useUiStore();

    expect(store.expandedNavGroups).toEqual(['navigation.trails']);
  });

  it('toggleNavGroup expands a group and persists the choice', () => {
    const store = useUiStore();

    store.toggleNavGroup('navigation.trails');

    expect(store.expandedNavGroups).toEqual(['navigation.trails']);
    expect(localStorage.getItem(NAV_EXPANDED_GROUPS_STORAGE_KEY)).toBe('navigation.trails');
  });

  it('toggleNavGroup closes whichever group was open — one at a time', () => {
    const store = useUiStore();

    store.toggleNavGroup('navigation.trails');
    store.toggleNavGroup('season');

    expect(store.expandedNavGroups).toEqual(['season']);
    expect(localStorage.getItem(NAV_EXPANDED_GROUPS_STORAGE_KEY)).toBe('season');
  });

  it('toggleNavGroup collapses the open group when it is toggled again', () => {
    const store = useUiStore();

    store.toggleNavGroup('navigation.trails');
    store.toggleNavGroup('navigation.trails');

    expect(store.expandedNavGroups).toEqual([]);
    expect(localStorage.getItem(NAV_EXPANDED_GROUPS_STORAGE_KEY)).toBe('');
  });

  it('starts with the menu button unpinned', () => {
    expect(useUiStore().isMenuButtonPinned).toBe(false);
  });

  it('unpinning brings the rail back, so the navigation is never stranded off screen', () => {
    const store = useUiStore();
    store.toggleMenuButtonPinned();
    store.toggleNavCollapsed();

    expect(store.isNavCollapsed).toBe(true);

    store.toggleMenuButtonPinned();

    // The button was the only way to reopen the rail; removing it must restore the rail.
    expect(store.isMenuButtonPinned).toBe(false);
    expect(store.isNavCollapsed).toBe(false);
    expect(localStorage.getItem(MENU_BUTTON_PINNED_STORAGE_KEY)).toBe('0');
  });
});
