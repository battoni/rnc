import type { PublicLead, UpdateDiagnosticPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The contact and the later steps — each saved as the visitor leaves it (R11). */
export default async function updateDiagnostic(
  token: string,
  payload: UpdateDiagnosticPayload
): Promise<ApiResponse<PublicLead>> {
  return API({ endpoint: `public/diagnostics/${encodeURIComponent(token)}`, method: 'patch', payload });
}
