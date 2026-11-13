import type { PartnerAccess } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The partner's login deactivated and signed out everywhere; answers it as it is now. */
export default async function revokePartnerAccess(partnerId: number): Promise<ApiResponse<PartnerAccess>> {
  return API({ endpoint: `partners/${partnerId}/access`, forwardError: true, method: 'delete' });
}
