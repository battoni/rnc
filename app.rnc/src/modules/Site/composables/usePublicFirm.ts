import { readonly, ref } from 'vue';
import type { PublicFirm } from '../interfaces';
import { currentBrand, useFirmBrand } from '@Composables';
import { GetPublicFirmService } from '../services';

/*
 * Shared by every public page: the header, the footer and the SEO tags all read the same firm, and
 * it is asked for once per page load — again only when the tab's sub-brand changed since (rnc-3 R2).
 * A failed request is forgotten, so the next page asks again.
 *
 * The main brand's firm IS the host firm (rnc-4 D10): its answer comes from `useFirmBrand`'s one boot
 * read, never a second request, and so does `isFirmMissing` (R3). Only a sub-brand is asked here.
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
  const { isFirmMissing, load: loadHostFirm } = useFirmBrand();

  // EVENTS
  function load(): Promise<void> {
    const brand = currentBrand();

    if (firm.value && loadedFor === brand) return Promise.resolve();

    if (!pendingRequest || pendingFor !== brand) {
      pendingFor = brand;
      isBrandPending.value = brand !== null;
      pendingRequest =
        brand === null ? loadHostFirm().then(onHostFirm) : GetPublicFirmService().then(onLoaded).catch(onFailed);
    }

    return pendingRequest;
  }

  /** A host the API does not know is `useFirmBrand`'s to say (R3): a failed sub-brand read is forgotten. */
  function onFailed() {
    pendingRequest = null;
    isBrandPending.value = false;
  }

  function onHostFirm(hostFirm: PublicFirm | null) {
    if (!hostFirm) {
      onFailed();
      return;
    }

    onLoaded({ data: hostFirm });
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
    isFirmMissing,
    load,
  };
}

export { usePublicFirm };
