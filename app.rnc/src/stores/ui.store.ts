import { useWindowSize } from '@vueuse/core';
import { defineStore } from 'pinia';
import {
  FULLSCREEN_STORAGE_KEY,
  MENU_BUTTON_PINNED_STORAGE_KEY,
  NAV_COLLAPSED_STORAGE_KEY,
  NAV_EXPANDED_GROUPS_STORAGE_KEY,
} from '@Constants';

interface UiState {
  expandedNavGroups: string[];
  isFullscreen: boolean;
  isMenuButtonPinned: boolean;
  isNavCollapsed: boolean;
  isSidenavOpen: boolean;
}

const { height, width } = useWindowSize();

function readStoredFullscreen(): boolean {
  return localStorage.getItem(FULLSCREEN_STORAGE_KEY) === '1';
}

/** Both opt-in: a fresh install shows the rail and keeps the button out of the way. */
function readStoredMenuButtonPinned(): boolean {
  return localStorage.getItem(MENU_BUTTON_PINNED_STORAGE_KEY) === '1';
}

/**
 * A collapsed rail is only reopenable from the menu button, and the button only shows at every
 * width when it is pinned. The pair is kept consistent on write, but nothing guaranteed it on
 * read — a stored combination from an older build, another tab, or a hand-edited value could
 * start the app with no navigation and no way to ask for it. So the invariant is re-checked
 * here: collapsed without pinned is not a state, it is a trap, and it opens.
 */
function readStoredNavCollapsed(): boolean {
  const isCollapsed = localStorage.getItem(NAV_COLLAPSED_STORAGE_KEY) === '1';

  if (isCollapsed && !readStoredMenuButtonPinned()) return false;

  return isCollapsed;
}

/**
 * Group keys are route names or i18n labels — neither ever contains a comma, so the list
 * round-trips as a comma-joined string with nothing to parse and nothing that can throw.
 *
 * One open group at a time is an invariant, and storage is older than the invariant: a value
 * written before the accordion — or by a tab still running an older build — can name several
 * groups. The first one wins, so the app never boots into a state its own rule forbids.
 */
function readStoredNavExpandedGroups(): string[] {
  const stored = localStorage.getItem(NAV_EXPANDED_GROUPS_STORAGE_KEY);

  if (!stored) return [];

  return stored.split(',').slice(0, 1);
}

function persistFullscreen(isFullscreen: boolean) {
  localStorage.setItem(FULLSCREEN_STORAGE_KEY, isFullscreen ? '1' : '0');
}

function persistMenuButtonPinned(isMenuButtonPinned: boolean) {
  localStorage.setItem(MENU_BUTTON_PINNED_STORAGE_KEY, isMenuButtonPinned ? '1' : '0');
}

function persistNavCollapsed(isNavCollapsed: boolean) {
  localStorage.setItem(NAV_COLLAPSED_STORAGE_KEY, isNavCollapsed ? '1' : '0');
}

function persistNavExpandedGroups(expandedNavGroups: string[]) {
  localStorage.setItem(NAV_EXPANDED_GROUPS_STORAGE_KEY, expandedNavGroups.join(','));
}

export default defineStore('ui', {
  state: (): UiState => ({
    expandedNavGroups: readStoredNavExpandedGroups(),
    isFullscreen: readStoredFullscreen(),
    isMenuButtonPinned: readStoredMenuButtonPinned(),
    isNavCollapsed: readStoredNavCollapsed(),
    isSidenavOpen: false,
  }),
  actions: {
    closeSidenav() {
      this.isSidenavOpen = false;
    },
    /**
     * Keep the menu button on screen at every width. Unpinning also brings the rail back:
     * the button is the only way to reopen it, so leaving the rail hidden without it would
     * strand the navigation off screen.
     */
    toggleMenuButtonPinned() {
      this.isMenuButtonPinned = !this.isMenuButtonPinned;

      persistMenuButtonPinned(this.isMenuButtonPinned);

      if (!this.isMenuButtonPinned && this.isNavCollapsed) this.toggleNavCollapsed();
    },
    /**
     * Fold the navigation rail away or bring it back. Collapsing also shuts the drawer, so
     * the two never claim the screen at once.
     */
    toggleNavCollapsed() {
      this.isNavCollapsed = !this.isNavCollapsed;
      this.isSidenavOpen = false;

      persistNavCollapsed(this.isNavCollapsed);
    },
    /**
     * Expand one navigation group and collapse whichever was open — the navigation holds one
     * open group at a time. Several open at once push everything below them past the fold, so
     * the group a person just asked for is the one that has room. The choice is remembered:
     * the rail reopens the way it was left, across reloads and across the rail/drawer pair.
     */
    toggleNavGroup(groupKey: string) {
      const isExpanded = this.expandedNavGroups.includes(groupKey);

      this.expandedNavGroups = isExpanded ? [] : [groupKey];

      persistNavExpandedGroups(this.expandedNavGroups);
    },
    setFullscreen(isFullscreen: boolean) {
      this.isFullscreen = isFullscreen;

      persistFullscreen(isFullscreen);
    },
    toggleFullscreen() {
      this.setFullscreen(!this.isFullscreen);
    },
    toggleSidenav() {
      this.isSidenavOpen = !this.isSidenavOpen;
    },
  },
  getters: {
    appHeight: () => height.value,
    appWidth: () => width.value,
  },
});
