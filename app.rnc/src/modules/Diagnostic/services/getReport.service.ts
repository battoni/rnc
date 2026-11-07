import type { PublicReport } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The "Relatório de Impacto", by its own token (AM4). */
export default async function getReport(reportToken: string): Promise<ApiResponse<PublicReport>> {
  return API({ endpoint: `public/reports/${encodeURIComponent(reportToken)}`, method: 'get' });
}
