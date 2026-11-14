import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** rnc-4 R39: everything the firm keeps about a lead — its fields, consents and timeline — as JSON. */
export default async function exportLead(id: number): Promise<ApiResponse<Record<string, unknown>>> {
  return API({ endpoint: `leads/${id}/export`, forwardError: true, method: 'get' });
}
