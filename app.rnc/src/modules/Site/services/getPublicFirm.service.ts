import type { PublicFirm } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getPublicFirm(): Promise<ApiResponse<PublicFirm>> {
  return API({
    endpoint: 'public/firm',
    method: 'get',
  });
}
