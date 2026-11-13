import type { PartnerMatches } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Every active partner scored against the lead, and whether it can be referred at all. */
export default async function getPartnerMatches(leadId: number): Promise<ApiResponse<PartnerMatches>> {
  return API({ endpoint: `leads/${leadId}/partner-matches`, forwardError: true, method: 'get' });
}
