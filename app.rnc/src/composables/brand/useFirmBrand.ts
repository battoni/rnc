import { computed, readonly, ref } from 'vue';
import type { PublicFirm } from '@Interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';
import { FIRM_BRAND_STORAGE_KEY } from './constants';

/*
 * The host firm's brand for the whole app (rnc-4 R10, D10): the firm the address names, its palette,
 * its logo text and the product's name (`platform.name`, R9). One `GET public/firm` per page load,
 * WITHOUT `X-Brand` — a sub-brand is the public site's alone (`useSubBrandTheme`), and the back office
 * of a firm is the firm's whatever the tab last browsed.
 *
 * The answer is kept for the tab (`sessionStorage`, one origin per firm host), and the store starts
 * from it: a second page of the visit paints the firm's colours and names before the API answers,
 * never the neutral theme's (A11). The first visit of a tab shows the neutral theme until it answers.
 *
 * `isFirmMissing` (rnc-4 R3) is the boot read's 404 `firms.notFound`: the host names a firm the API
 * does not know, and `TheRouteGuard` swaps every path for "Escritório não encontrado". Any other
 * failure leaves it false — an outage is not a missing firm — and the next `load()` asks again.
 */
interface ApiFailure {
  response?: { data?: { error?: unknown }; status?: number };
}

const FIRM_NOT_FOUND_ERROR = 'firms.notFound';
const HTTP_NOT_FOUND = 404;

const firm = ref<PublicFirm | null>(readStoredFirm());
const isFirmMissing = ref(false);

let isLoaded = false;
let pendingRequest: Promise<PublicFirm | null> | null = null;

// HELPERS
function readStoredFirm(): PublicFirm | null {
  try {
    const stored = JSON.parse(sessionStorage.getItem(FIRM_BRAND_STORAGE_KEY) ?? 'null') as PublicFirm | null;

    return stored && typeof stored.slug === 'string' ? stored : null;
  } catch {
    return null;
  }
}

function storeFirm(current: PublicFirm) {
  try {
    sessionStorage.setItem(FIRM_BRAND_STORAGE_KEY, JSON.stringify(current));
  } catch {
    // Without storage the next page simply waits for the API.
  }
}

/** The firm of the address, its brand and the product's name, read once per page load. */
function useFirmBrand() {
  const logoText = computed(() => firm.value?.logo_text ?? '');
  const platformName = computed(() => firm.value?.platform?.name ?? '');

  // EVENTS
  function load(): Promise<PublicFirm | null> {
    if (isLoaded) return Promise.resolve(firm.value);

    pendingRequest ??= API({ endpoint: 'public/firm', method: 'get' }).then(onLoaded).catch(onFailed);

    return pendingRequest;
  }

  function onFailed(failure: ApiFailure): null {
    pendingRequest = null;

    const isUnknownFirm =
      failure?.response?.status === HTTP_NOT_FOUND && failure.response.data?.error === FIRM_NOT_FOUND_ERROR;

    if (isUnknownFirm) isFirmMissing.value = true;

    return null;
  }

  function onLoaded({ data }: ApiResponse<PublicFirm>): PublicFirm {
    firm.value = data;
    isLoaded = true;
    pendingRequest = null;

    storeFirm(data);

    return data;
  }

  return {
    firm: readonly(firm),
    isFirmMissing: readonly(isFirmMissing),
    load,
    logoText,
    platformName,
  };
}

export { useFirmBrand };
