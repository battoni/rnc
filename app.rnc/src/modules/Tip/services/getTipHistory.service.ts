import type { TipHistoryEntry } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getTipHistory(id: number): Promise<ApiResponse<TipHistoryEntry[]>> {
  return API({
    endpoint: `tips/${id}/history`,
    method: 'get',
  });
}
