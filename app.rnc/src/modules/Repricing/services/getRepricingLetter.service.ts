import type { RepricingLetter } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** What the branded letter of repricing prints for one client. */
export default async function getRepricingLetter(id: number): Promise<ApiResponse<RepricingLetter>> {
  return API({ endpoint: `repricing/${id}/letter`, method: 'get' });
}
