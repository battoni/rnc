import type { SectorPageSummary } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '@Composables';

export default async function getSectorPages(): Promise<ApiResponse<SectorPageSummary[]>> {
  return publicApi({
    endpoint: 'public/sectors',
    method: 'get',
  });
}
