import type { SectorPage } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getSectorPage(slug: string): Promise<ApiResponse<SectorPage>> {
  return API({
    endpoint: `public/sectors/${encodeURIComponent(slug)}`,
    method: 'get',
  });
}
