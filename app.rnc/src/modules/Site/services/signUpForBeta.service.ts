import type { BetaSignupPayload, BetaSignupResult } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@Composables';

/** A firm joins the beta from the root site (rnc-4 R29): a `beta` lead on the platform firm only. */
export default async function signUpForBeta(payload: BetaSignupPayload): Promise<ApiResponse<BetaSignupResult>> {
  return publicApi({ endpoint: 'public/beta-signups', method: 'post', payload });
}
