import type { PublicLead, UpdateDiagnosticPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@SiteModule';

/** The contact and the later steps — each saved as the visitor leaves it (R11). */
export default async function updateDiagnostic(
  token: string,
  payload: UpdateDiagnosticPayload
): Promise<ApiResponse<PublicLead>> {
  return publicApi({ endpoint: `public/diagnostics/${encodeURIComponent(token)}`, method: 'patch', payload });
}
