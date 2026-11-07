import type { Availability } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The specialists' free slots over the next 10 working days (R15). */
export default async function getAvailability(token: string): Promise<ApiResponse<Availability>> {
  return API({ endpoint: `public/diagnostics/${encodeURIComponent(token)}/availability`, method: 'get' });
}
