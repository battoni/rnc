import { onBeforeUnmount, onMounted, watch } from 'vue';
import type { DeepReadonly, Ref } from 'vue';
import type { PublicFirm } from '@Interfaces';
import { brandPalette } from './brandPalette';
import { BRAND_PALETTE_STORAGE_KEY } from './constants';
import { currentBrand } from './publicBrand';
import { useFirmBrand } from './useFirmBrand';

/*
 * The brand's colours over the whole document (rnc-4 R10, D10; rnc-3 R3).
 *
 * Two layers, one painter. `useBrandTheme()` is mounted once by `App.vue` and paints the HOST FIRM's
 * palette on both layouts — the back office no longer takes its brand colours from
 * `VITE_ACTIVE_THEME`, whose `hub` theme is only the neutral ground before a firm answers.
 * `useSubBrandTheme(firm)` is called by `ThePublicLayout`: while a public page is up, a sub-brand's
 * palette is painted over the firm's; when the last public page goes, the firm's comes back after the
 * route fade, never mid-swap.
 *
 * On `<html>`, not on a layout's own element: PrimeVue's tokens (`--p-primary-*`, the buttons' fills),
 * the dark scheme's brand word (`--color-primary-text`) and every overlay teleported to `<body>` are
 * declared on the root and resolve there — a ramp set lower down would re-theme the utilities and
 * leave PrimeVue's buttons and the dark scheme's links in the theme's colours. Inline on the root
 * outvotes the theme's `@theme` block and re-resolves everything declared beside it.
 *
 * Both layers paint from the tab's last answer before the API replies (no flash): the firm from
 * `useFirmBrand`'s stored answer, the sub-brand from its own stored palette.
 *
 * The document title is the product's name (`platform.name`, R9) wherever no public page is up: a
 * public page writes its own (`useSeo`).
 */
interface StoredPalette {
  accent: string;
  primary: string;
  slug: string;
}

/** Longer than the route fade's out-in swap, during which no public layout is mounted. */
const HAND_BACK_DELAY_MS = 1000;

/** What `brandPalette` writes, and nothing else on the root is touched. */
const PALETTE_PREFIX = '--color-primary-';

let firmPalette: StoredPalette | null = null;
let mountedPublicLayouts = 0;
let subBrandPalette: StoredPalette | null = null;

// HELPERS
function readStoredPalette(): StoredPalette | null {
  try {
    const stored = JSON.parse(sessionStorage.getItem(BRAND_PALETTE_STORAGE_KEY) ?? 'null') as StoredPalette | null;

    return stored && typeof stored.slug === 'string' ? stored : null;
  } catch {
    return null;
  }
}

function storePalette(palette: StoredPalette | null) {
  try {
    if (palette) sessionStorage.setItem(BRAND_PALETTE_STORAGE_KEY, JSON.stringify(palette));
    else sessionStorage.removeItem(BRAND_PALETTE_STORAGE_KEY);
  } catch {
    // Without storage the next page simply waits for the API.
  }
}

function clearPalette() {
  const root = document.documentElement;

  [...root.style]
    .filter((property) => property.startsWith(PALETTE_PREFIX))
    .forEach((property) => root.style.removeProperty(property));
  delete root.dataset.brand;
}

/** The sub-brand's palette while a public page shows one, else the firm's, else the theme's own. */
function paintCurrent() {
  const shown = (mountedPublicLayouts > 0 && subBrandPalette) || firmPalette;

  if (!shown) {
    clearPalette();
    return;
  }

  const root = document.documentElement;

  Object.entries(brandPalette(shown.primary, shown.accent)).forEach(([property, value]) =>
    root.style.setProperty(property, value)
  );
  root.dataset.brand = shown.slug;
}

function toPalette(firm: DeepReadonly<PublicFirm>, slug: string): StoredPalette | null {
  const hasColours = Boolean(firm.brand_primary && firm.brand_accent);

  return hasColours ? { accent: firm.brand_accent, primary: firm.brand_primary, slug } : null;
}

function showPlatformTitle(platformName: string) {
  const isPublicPageUp = mountedPublicLayouts > 0;

  if (isPublicPageUp || !platformName) return;

  document.title = platformName;
}

/** Mounted once, by `App.vue`: the host firm's palette and the product's title on every layout. */
function useBrandTheme() {
  const { firm, load, platformName } = useFirmBrand();

  watch(firm, onFirmChange, { immediate: true });
  watch(platformName, showPlatformTitle, { immediate: true });

  onMounted(onComponentMount);

  // EVENTS
  function onComponentMount() {
    load();
  }

  function onFirmChange(current: DeepReadonly<PublicFirm> | null) {
    firmPalette = current ? toPalette(current, current.slug) : null;

    paintCurrent();
  }
}

/** Called once by `ThePublicLayout` with the firm it shows: a sub-brand's colours, public pages only. */
function useSubBrandTheme(firm: Readonly<Ref<DeepReadonly<PublicFirm> | null>>) {
  const { platformName } = useFirmBrand();

  watch(firm, onFirmChange);

  onMounted(onComponentMount);
  onBeforeUnmount(onComponentUnmount);

  // HELPERS
  function handBack() {
    if (mountedPublicLayouts > 0) return;

    paintCurrent();
    showPlatformTitle(platformName.value);
  }

  // EVENTS
  function onComponentMount() {
    mountedPublicLayouts += 1;

    const stored = readStoredPalette();
    const brand = currentBrand();
    const isStoredForTab = brand !== null && stored?.slug === brand;

    if (firm.value) {
      onFirmChange(firm.value);
      return;
    }

    subBrandPalette = isStoredForTab ? stored : null;

    paintCurrent();
  }

  function onComponentUnmount() {
    mountedPublicLayouts -= 1;

    setTimeout(handBack, HAND_BACK_DELAY_MS);
  }

  function onFirmChange(current: DeepReadonly<PublicFirm> | null) {
    if (!current) return;

    subBrandPalette = current.brand ? toPalette(current, current.brand) : null;

    storePalette(subBrandPalette);
    paintCurrent();
  }
}

export { useBrandTheme, useSubBrandTheme };
