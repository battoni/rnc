import type { Availability } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@SiteModule';

/** The specialists' free slots over the next 10 working days (R15). */
export default async function getAvailability(token: string): Promise<ApiResponse<Availability>> {
  return publicApi({ endpoint: `public/diagnostics/${encodeURIComponent(token)}/availability`, method: 'get' });
}
