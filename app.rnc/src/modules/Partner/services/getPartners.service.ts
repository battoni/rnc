import type { Partner } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The registry: every partner of the firm, active first. */
export default async function getPartners(): Promise<ApiResponse<Partner[]>> {
  return API({ endpoint: 'partners', method: 'get' });
}
