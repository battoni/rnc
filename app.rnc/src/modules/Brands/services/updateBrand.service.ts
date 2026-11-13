import type { Brand, BrandPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** An edit; answers the brand as it is now. A refusal comes back by field (`brand.lowContrast`). */
export default async function updateBrand(id: number, payload: BrandPayload): Promise<ApiResponse<Brand>> {
  return API({ endpoint: `brands/${id}`, forwardError: true, method: 'patch', payload });
}
