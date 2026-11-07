import { COLOR_SCHEME } from '@Enums';

/**
 * Active brand theme — single source of truth is the VITE_ACTIVE_THEME env var.
 * The CSS import (src/styles/theme/colors.css) resolves the same var through the
 * `@ActiveTheme` Vite alias, so both stay in sync from one place. Used here for
 * non-CSS concerns (e.g. picking brand assets).
 */
const ACTIVE_THEME = import.meta.env.VITE_ACTIVE_THEME || 'battoni-dev';

/**
 * Default color scheme when the user has no stored preference.
 *
 * Decided in TWO places: here, and in the anti-FOUC script in `index.html`, which resolves the
 * same default before the app boots (no stored preference = follow the system). Change one and
 * not the other, and the first paint shows one scheme and the app switches to the other.
 */
const DEFAULT_COLOR_SCHEME = COLOR_SCHEME.SYSTEM;

/**
 * localStorage key for the persisted color-scheme preference.
 * NOTE: kept in sync with the anti-FOUC inline script in index.html.
 */
const COLOR_SCHEME_STORAGE_KEY = 'app.rnc:color-scheme';

/**
 * Tailwind's `lg`. Above it the persistent navigation rail exists and the drawer does not —
 * ONavbarDrawer carries `lg:hidden`, so opening it on a wide screen shows the mask over a
 * panel that is never painted.
 */
const DESKTOP_BREAKPOINT_PX = 1024;

/**
 * Tailwind's `md`. A different axis from DESKTOP_BREAKPOINT_PX on purpose: `lg` decides whether
 * the rail exists at all, this one decides whether the drawer covers the whole screen. Also
 * where a layout stops being a phone: `useViewport` reads it to decide whether the page header
 * collapses its actions behind a `⋮`.
 */
const MOBILE_BREAKPOINT_PX = 768;

/**
 * localStorage key for pinning the menu button. Pinned, the button is always on screen and
 * the navigation becomes something you open and close at will; unpinned, it only appears in
 * the tablet range where the rail does not fit.
 */
const MENU_BUTTON_PINNED_STORAGE_KEY = 'app.rnc:menu-button-pinned';

/** localStorage key for the folded-away navigation rail. */
const NAV_COLLAPSED_STORAGE_KEY = 'app.rnc:nav-collapsed';

/**
 * localStorage key for the navigation group left open. One at a time, so this holds a list of
 * at most one — a list rather than a string because the storage predates the rule, and a value
 * written by an older build can still name several.
 */
const NAV_EXPANDED_GROUPS_STORAGE_KEY = 'app.rnc:nav-expanded-groups';

/** localStorage key for full-width layout preference. */
const FULLSCREEN_STORAGE_KEY = 'app.rnc:fullscreen';

/** localStorage key for contextual tutor tips visibility. */
const TUTOR_STORAGE_KEY = 'app.rnc:tutor-enabled';

export {
  ACTIVE_THEME,
  COLOR_SCHEME_STORAGE_KEY,
  DEFAULT_COLOR_SCHEME,
  DESKTOP_BREAKPOINT_PX,
  FULLSCREEN_STORAGE_KEY,
  MENU_BUTTON_PINNED_STORAGE_KEY,
  MOBILE_BREAKPOINT_PX,
  NAV_COLLAPSED_STORAGE_KEY,
  NAV_EXPANDED_GROUPS_STORAGE_KEY,
  TUTOR_STORAGE_KEY,
};
