import type { Partner, PartnerPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A new partner; answers it as stored. */
export default async function createPartner(payload: PartnerPayload): Promise<ApiResponse<Partner>> {
  return API({ endpoint: 'partners', forwardError: true, method: 'post', payload });
}
