import type { IssuedPartnerAccess, PartnerAccessPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A partner's portal login (rnc-2 R2); answers it with the generated password, once. */
export default async function createPartnerAccess(
  partnerId: number,
  payload: PartnerAccessPayload
): Promise<ApiResponse<IssuedPartnerAccess>> {
  return API({ endpoint: `partners/${partnerId}/access`, forwardError: true, method: 'post', payload });
}
