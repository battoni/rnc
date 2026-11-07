import type { PortalReferral, TransitionReferralPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** One step of the graph (`POST portal/referrals/{id}/transition`); answers the referral as it now stands. */
export default async function transitionReferral(
  id: number,
  payload: TransitionReferralPayload
): Promise<ApiResponse<PortalReferral>> {
  return API({ endpoint: `portal/referrals/${id}/transition`, forwardError: true, method: 'post', payload });
}
