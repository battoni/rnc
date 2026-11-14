import type { BetaConsent } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@Composables';

/** The words the beta form asks consent under: the platform firm's current version (rnc-4 R29, W61). */
export default async function getBetaConsent(): Promise<ApiResponse<BetaConsent>> {
  return publicApi({ endpoint: 'public/beta-signups/consent', method: 'get' });
}
