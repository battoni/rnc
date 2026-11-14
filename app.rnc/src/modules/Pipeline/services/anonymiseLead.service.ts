import type { LeadDetail } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** rnc-4 R39: wipes who the lead is, for good; answers the anonymised detail (a second call, the same). */
export default async function anonymiseLead(id: number): Promise<ApiResponse<LeadDetail>> {
  return API({ endpoint: `leads/${id}/anonymise`, forwardError: true, method: 'post' });
}
