import type { Tip } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getTips(includeArchived = false): Promise<ApiResponse<Tip[]>> {
  return API({
    endpoint: `tips?include_archived=${includeArchived ? 'true' : 'false'}`,
    method: 'get',
  });
}
