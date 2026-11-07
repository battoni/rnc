import type { SectorPageSummary } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getSectorPages(): Promise<ApiResponse<SectorPageSummary[]>> {
  return API({
    endpoint: 'public/sectors',
    method: 'get',
  });
}
