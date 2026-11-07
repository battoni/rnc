import type { RepricingClientDetail } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** One client with the diagnostic items behind its uplift. */
export default async function getRepricingClient(id: number): Promise<ApiResponse<RepricingClientDetail>> {
  return API({ endpoint: `repricing/${id}`, method: 'get' });
}
