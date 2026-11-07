import type { Tip } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function unarchiveTip(id: number): Promise<ApiResponse<Tip>> {
  return API({
    endpoint: `tips/${id}/unarchive`,
    method: 'patch',
  });
}
