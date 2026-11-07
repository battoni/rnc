import { readonly, ref } from 'vue';
import type { ApiParams } from '@Providers';
import { API } from '@Providers';
import {
  BRAND_HEADER,
  BRAND_NOT_FOUND_CODE,
  BRAND_PREVIEW_STORAGE_KEY,
  BRAND_QUERY,
  BRAND_SLUG_PATTERN,
  BRAND_STORAGE_KEY,
} from '../constants';

/*
 * The sub-brand of this tab (rnc-3 R2, D2). There is one local host, so the brand rides in the
 * address once — `?marca=<slug>`, `?previa=1` for the operator's "Ver como" — and the tab keeps it in
 * `sessionStorage`; `?marca=franco` is the main brand and drops it. `sessionStorage` is the truth, so
 * every reader agrees, and the address bar is read on every ask: whatever page the visitor lands on
 * with `?marca=` wins.
 *
 * `publicApi` is the one way a public call leaves (Site and Diagnostic services): it adds `X-Brand`,
 * and on the API's 404 `brands.notFound` — a brand since deactivated, a typo — it drops the brand and
 * asks the same call again as the main brand (AM21, C1), so a stale tab lands on Franco, not an error.
 * The events beacon cannot carry a header and does not need one: the API counts it on the main brand.
 */
interface ApiFailure {
  response?: { data?: { error?: unknown }; status?: number };
}

const HTTP_NOT_FOUND = 404;

/** The brand the tab is on, for whoever must react when it changes (the layout re-reads the firm). */
const storedBrand = ref<string | null>(null);

// HELPERS
function readStorage(key: string): string | null {
  try {
    return sessionStorage.getItem(key);
  } catch {
    return null;
  }
}

function writeStorage(key: string, value: string | null) {
  try {
    if (value === null) sessionStorage.removeItem(key);
    else sessionStorage.setItem(key, value);
  } catch {
    // A browser that refuses storage keeps the brand for this page only, read from the address.
  }
}

function adoptAddress() {
  const params = new URLSearchParams(window.location.search);
  const asked = params.get(BRAND_QUERY.brand);

  if (asked === null) return;

  const slug = asked.trim().toLowerCase();

  if (slug === BRAND_QUERY.main || !BRAND_SLUG_PATTERN.test(slug)) {
    writeStorage(BRAND_STORAGE_KEY, null);
    writeStorage(BRAND_PREVIEW_STORAGE_KEY, null);
    return;
  }

  writeStorage(BRAND_STORAGE_KEY, slug);
  writeStorage(BRAND_PREVIEW_STORAGE_KEY, params.get(BRAND_QUERY.preview) === BRAND_QUERY.previewOn ? '1' : null);
}

function isBrandRefusal(failure: ApiFailure): boolean {
  return failure.response?.status === HTTP_NOT_FOUND && failure.response.data?.error === BRAND_NOT_FOUND_CODE;
}

/** The sub-brand slug this tab is shown under, or null for the main brand. */
function currentBrand(): string | null {
  adoptAddress();

  storedBrand.value = readStorage(BRAND_STORAGE_KEY);

  return storedBrand.value;
}

/** True on the operator's "Ver como" tab (`?previa=1` beside `?marca=`, R5). */
function isBrandPreview(): boolean {
  return currentBrand() !== null && readStorage(BRAND_PREVIEW_STORAGE_KEY) === '1';
}

/** Back to the main brand: out of the tab's storage and out of the address bar. */
function forgetBrand() {
  writeStorage(BRAND_STORAGE_KEY, null);
  writeStorage(BRAND_PREVIEW_STORAGE_KEY, null);

  const url = new URL(window.location.href);

  if (url.searchParams.has(BRAND_QUERY.brand) || url.searchParams.has(BRAND_QUERY.preview)) {
    url.searchParams.delete(BRAND_QUERY.brand);
    url.searchParams.delete(BRAND_QUERY.preview);
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
  }

  storedBrand.value = null;
}

/** A public call (`public/*`) under the tab's brand: `X-Brand`, and the main brand when it is refused. */
async function publicApi(params: ApiParams) {
  const brand = currentBrand();

  if (!brand) return API(params);

  return API({ ...params, headers: { ...params.headers, [BRAND_HEADER]: brand } }).catch((failure: ApiFailure) => {
    if (!isBrandRefusal(failure)) throw failure;

    forgetBrand();

    return API(params);
  });
}

const brandOfTab = readonly(storedBrand);

export { brandOfTab, currentBrand, forgetBrand, isBrandPreview, publicApi };
