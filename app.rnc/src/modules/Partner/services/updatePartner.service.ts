import type { Partner, UpdatePartnerPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** An edit or the active switch; answers the partner as it is now. */
export default async function updatePartner(id: number, payload: UpdatePartnerPayload): Promise<ApiResponse<Partner>> {
  return API({ endpoint: `partners/${id}`, forwardError: true, method: 'patch', payload });
}
