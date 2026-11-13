import type { LeadDetail } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The lead detail: profile, the full diagnostic, consent, meeting, timeline and notes. */
export default async function getLead(id: number): Promise<ApiResponse<LeadDetail>> {
  return API({ endpoint: `leads/${id}`, forwardError: true, method: 'get' });
}
