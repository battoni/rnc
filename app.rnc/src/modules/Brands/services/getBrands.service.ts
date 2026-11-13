import type { BrandList } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The console: the main brand and every sub-brand of the firm, each with its lead count. */
export default async function getBrands(): Promise<ApiResponse<BrandList>> {
  return API({ endpoint: 'brands', method: 'get' });
}
