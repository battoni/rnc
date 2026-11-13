import type { IssuedPartnerAccess } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A new password for the partner's login, answered once; its old sessions end. */
export default async function regeneratePartnerPassword(partnerId: number): Promise<ApiResponse<IssuedPartnerAccess>> {
  return API({ endpoint: `partners/${partnerId}/access/password`, forwardError: true, method: 'post' });
}
