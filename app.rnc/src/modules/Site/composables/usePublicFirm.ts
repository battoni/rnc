import { readonly, ref } from 'vue';
import type { PublicFirm } from '../interfaces';
import { GetPublicFirmService } from '../services';
import { currentBrand } from './publicBrand';

/*
 * Shared by every public page: the header, the footer and the SEO tags all read the same firm, and
 * it is asked for once per page load — again only when the tab's sub-brand changed since (rnc-3 R2).
 * A failed request is forgotten, so the next page asks again.
 */
const firm = ref<PublicFirm | null>(null);

/*
 * True while the tab's sub-brand is asked for and not answered yet. Every point of the shell that
 * writes the brand — the marks, the footer's tagline, the copyright, the landing's kicker and proof —
 * waits unseen on this one flag, so Franco's words never flash on an Express tab (rnc-3 W38, review
 * S1). A failure clears it: the main brand's words are better than an empty shell.
 */
const isBrandPending = ref(false);

/*
 * True once the boot read answered 404 `firms.notFound` (rnc-4 R3): the host names a firm the API
 * does not know, and `TheRouteGuard` shows "Escritório não encontrado" on every path. Any other
 * failure leaves it false — an outage is not a missing firm.
 */
const isFirmMissing = ref(false);

const FIRM_NOT_FOUND_ERROR = 'firms.notFound';
const HTTP_NOT_FOUND = 404;

let loadedFor: string | null = null;
let pendingRequest: Promise<void> | null = null;
let pendingFor: string | null = null;

/** The firm of the public site — its name, brand and public contact (`GET /api/public/firm`). */
function usePublicFirm() {
  // EVENTS
  function load(): Promise<void> {
    const brand = currentBrand();

    if (firm.value && loadedFor === brand) return Promise.resolve();

    if (!pendingRequest || pendingFor !== brand) {
      pendingFor = brand;
      isBrandPending.value = brand !== null;
      pendingRequest = GetPublicFirmService().then(onLoaded).catch(onFailed);
    }

    return pendingRequest;
  }

  function onFailed(failure: { response?: { data?: { error?: unknown }; status?: number } }) {
    pendingRequest = null;
    isBrandPending.value = false;

    const isUnknownFirm =
      failure?.response?.status === HTTP_NOT_FOUND && failure.response.data?.error === FIRM_NOT_FOUND_ERROR;

    if (isUnknownFirm) isFirmMissing.value = true;
  }

  function onLoaded({ data }: { data: PublicFirm }) {
    firm.value = data;
    // A refused brand was dropped on the way (AM21): the answer is the main brand's.
    loadedFor = data.brand ?? null;
    pendingRequest = null;
    isBrandPending.value = false;
  }

  return {
    firm: readonly(firm),
    isBrandPending: readonly(isBrandPending),
    isFirmMissing: readonly(isFirmMissing),
    load,
  };
}

export { usePublicFirm };
