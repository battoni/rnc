import type { ReadinessFilters, ReadinessList } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Prontidão da NF-e" (R18–R19): the hero over every listed client, the systems and the clients the filters keep. */
export default async function getReadiness(filters: ReadinessFilters): Promise<ApiResponse<ReadinessList>> {
  return API({
    endpoint: 'fiscal/readiness',
    method: 'get',
    query: { status: filters.status, system: filters.system },
  });
}
