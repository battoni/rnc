import type { PublicLead, StartDiagnosticPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Step 1 starts: a `visitante` lead and its token (rnc-1 AM3, AM13). */
export default async function startDiagnostic(payload: StartDiagnosticPayload): Promise<ApiResponse<PublicLead>> {
  return API({ endpoint: 'public/diagnostics', method: 'post', payload });
}
