import type { ViewAsSession } from '../interfaces';
import type { ApiResponse, PaginateResult } from '@Providers';
import { API } from '@Providers';

/** A page of the view-as log (rnc-4 R26), latest first. */
export default async function getViewAsLog(params: {
  page: number;
  per_page: number;
}): Promise<ApiResponse<PaginateResult<ViewAsSession>>> {
  return API({
    endpoint: 'platform/view-as-log',
    method: 'get',
    query: { page: params.page, per_page: params.per_page },
  });
}
