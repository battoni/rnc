import type { Idea, IdeaFilters } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Only the filters the person actually set travel: `buildQuery` drops an empty value, and `mine` is
 * sent only when on (as `1`) — off is the API's default.
 */
export default async function getIdeas(filters: Partial<IdeaFilters> = {}): Promise<ApiResponse<Idea[]>> {
  return API({
    endpoint: 'ideas',
    method: 'get',
    query: {
      area: filters.area,
      mine: filters.mine || undefined,
      search: filters.search,
      sort: filters.sort,
      status: filters.status,
      type: filters.type,
    },
  });
}
