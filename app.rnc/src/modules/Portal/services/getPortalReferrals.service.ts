import type { PortalReferral } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The signed-in partner's referrals, open before closed, newest first (`GET portal/referrals`, W22: unpaginated). */
export default async function getPortalReferrals(): Promise<ApiResponse<PortalReferral[]>> {
  return API({ endpoint: 'portal/referrals', method: 'get' });
}
