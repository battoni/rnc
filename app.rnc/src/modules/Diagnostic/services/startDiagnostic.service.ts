import type { PublicLead, StartDiagnosticPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@SiteModule';

/** Step 1 starts: a `visitante` lead and its token (rnc-1 AM3, AM13). */
export default async function startDiagnostic(payload: StartDiagnosticPayload): Promise<ApiResponse<PublicLead>> {
  return publicApi({ endpoint: 'public/diagnostics', method: 'post', payload });
}
