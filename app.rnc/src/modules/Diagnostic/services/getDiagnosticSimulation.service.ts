import type { DiagnosticSimulation } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The 2026–2033 tax mix and the split payment's cash effect (R14). */
export default async function getDiagnosticSimulation(token: string): Promise<ApiResponse<DiagnosticSimulation>> {
  return API({ endpoint: `public/diagnostics/${encodeURIComponent(token)}/simulation`, method: 'get' });
}
