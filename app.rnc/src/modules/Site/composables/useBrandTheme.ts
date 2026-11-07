import { onBeforeUnmount, onMounted, watch } from 'vue';
import type { DeepReadonly, Ref } from 'vue';
import type { PublicFirm } from '../interfaces';
import { BRAND_PALETTE_STORAGE_KEY } from '../constants';
import { brandPalette } from './brandPalette';
import { currentBrand } from './publicBrand';

/*
 * The sub-brand's colours over the whole document (rnc-3 R3), for as long as a public page is up.
 *
 * On `<html>`, not on the layout's own element: PrimeVue's tokens (`--p-primary-*`, the buttons'
 * fills), the dark scheme's brand word (`--color-primary-text`) and every overlay teleported to
 * `<body>` are declared on the root and resolve there — a ramp set lower down would re-theme the
 * utilities and leave the diagnostic's PrimeVue buttons and the dark scheme's links Franco's. Inline
 * on the root outvotes the theme's `@theme` block and re-resolves everything declared beside it.
 *
 * The colours of the last answer are kept for the tab, so the next page of the visit paints them on
 * mount instead of flashing Franco's navy until the API answers. When the last public page goes (the
 * back office stays Franco's, R3), the root is handed back after the route fade, never mid-swap.
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

let mountedLayouts = 0;

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

function paint({ accent, primary, slug }: StoredPalette) {
  const root = document.documentElement;

  Object.entries(brandPalette(primary, accent)).forEach(([property, value]) => root.style.setProperty(property, value));
  root.dataset.brand = slug;
}

function handBack() {
  const root = document.documentElement;

  [...root.style]
    .filter((property) => property.startsWith(PALETTE_PREFIX))
    .forEach((property) => root.style.removeProperty(property));
  delete root.dataset.brand;
}

/** Called once by `ThePublicLayout` with the firm it shows. */
function useBrandTheme(firm: Readonly<Ref<DeepReadonly<PublicFirm> | null>>) {
  watch(firm, onFirmChange);

  onMounted(onComponentMount);
  onBeforeUnmount(onComponentUnmount);

  // EVENTS
  function onComponentMount() {
    mountedLayouts += 1;

    const stored = readStoredPalette();
    const brand = currentBrand();

    if (firm.value) onFirmChange(firm.value);
    else if (brand && stored?.slug === brand) paint(stored);
  }

  function onComponentUnmount() {
    mountedLayouts -= 1;

    setTimeout(() => mountedLayouts === 0 && handBack(), HAND_BACK_DELAY_MS);
  }

  function onFirmChange(current: DeepReadonly<PublicFirm> | null) {
    if (!current) return;

    if (!current.brand) {
      storePalette(null);
      handBack();
      return;
    }

    const palette = { accent: current.brand_accent, primary: current.brand_primary, slug: current.brand };

    storePalette(palette);
    paint(palette);
  }
}

export { useBrandTheme };
