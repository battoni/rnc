import type { ReferLeadPayload, ReferralWritten } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The referral; answers the trail line and the timeline line it wrote. */
export default async function referLead(
  leadId: number,
  payload: ReferLeadPayload
): Promise<ApiResponse<ReferralWritten>> {
  return API({ endpoint: `leads/${leadId}/referrals`, forwardError: true, method: 'post', payload });
}
