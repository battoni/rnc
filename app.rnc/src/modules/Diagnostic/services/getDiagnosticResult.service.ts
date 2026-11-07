import type { DiagnosticResult } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The count, the titles and the ranges — never a detail (R13). */
export default async function getDiagnosticResult(token: string): Promise<ApiResponse<DiagnosticResult>> {
  return API({ endpoint: `public/diagnostics/${encodeURIComponent(token)}/result`, method: 'get' });
}
