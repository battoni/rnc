import type { Tip } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function restoreTipVersion(id: number, historyId: number): Promise<ApiResponse<Tip>> {
  return API({
    endpoint: `tips/${id}/restore/${historyId}`,
    method: 'post',
  });
}
