import type { Tip, TipPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function createTip(payload: TipPayload): Promise<ApiResponse<Tip>> {
  return API({
    endpoint: 'tips',
    method: 'post',
    payload,
  });
}
