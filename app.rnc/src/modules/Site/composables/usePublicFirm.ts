import { readonly, ref } from 'vue';
import type { PublicFirm } from '../interfaces';
import { GetPublicFirmService } from '../services';

/*
 * Shared by every public page: the header, the footer and the SEO tags all read the same firm, and
 * it is asked for once per page load. A failed request is forgotten, so the next page asks again.
 */
const firm = ref<PublicFirm | null>(null);

let pendingRequest: Promise<void> | null = null;

/** The firm of the public site — its name, brand and public contact (`GET /api/public/firm`). */
function usePublicFirm() {
  // EVENTS
  function load(): Promise<void> {
    if (firm.value) return Promise.resolve();

    pendingRequest ??= GetPublicFirmService().then(onLoaded).catch(onFailed);

    return pendingRequest;
  }

  function onFailed() {
    pendingRequest = null;
  }

  function onLoaded({ data }: { data: PublicFirm }) {
    firm.value = data;
  }

  return { firm: readonly(firm), load };
}

export { usePublicFirm };
