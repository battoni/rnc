import type { PublicReport } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@SiteModule';

/** The "Relatório de Impacto", by its own token (AM4). */
export default async function getReport(reportToken: string): Promise<ApiResponse<PublicReport>> {
  return publicApi({ endpoint: `public/reports/${encodeURIComponent(reportToken)}`, method: 'get' });
}
