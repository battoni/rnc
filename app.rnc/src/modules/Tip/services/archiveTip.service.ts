import type { Tip } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function archiveTip(id: number, changeReason: string): Promise<ApiResponse<Tip>> {
  return API({
    endpoint: `tips/${id}`,
    method: 'delete',
    payload: { change_reason: changeReason },
  });
}
