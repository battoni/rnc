import type { SectorPage } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '../composables/publicBrand';

export default async function getSectorPage(slug: string): Promise<ApiResponse<SectorPage>> {
  return publicApi({
    endpoint: `public/sectors/${encodeURIComponent(slug)}`,
    method: 'get',
  });
}
