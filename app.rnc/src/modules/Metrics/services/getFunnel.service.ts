import type { Funnel } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The funnel of the last `days` (7 or 30), with the tables by source and by sector page. */
export default async function getFunnel(days: number): Promise<ApiResponse<Funnel>> {
  return API({ endpoint: 'metrics/funnel', method: 'get', query: { days } });
}
