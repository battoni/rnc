import type { Tip, TipPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function updateTip(id: number, payload: TipPayload): Promise<ApiResponse<Tip>> {
  return API({
    endpoint: `tips/${id}`,
    method: 'patch',
    payload,
  });
}
