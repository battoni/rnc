import type { TipContent } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getActiveTips(): Promise<ApiResponse<TipContent[]>> {
  return API({
    endpoint: 'tips/active',
    method: 'get',
  });
}
