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

  function onFailed() {
    pendingRequest = null;
    isBrandPending.value = false;
  }

  function onLoaded({ data }: { data: PublicFirm }) {
    firm.value = data;
    // A refused brand was dropped on the way (AM21): the answer is the main brand's.
    loadedFor = data.brand ?? null;
    pendingRequest = null;
    isBrandPending.value = false;
  }

  return { firm: readonly(firm), isBrandPending: readonly(isBrandPending), load };
}

export { usePublicFirm };
