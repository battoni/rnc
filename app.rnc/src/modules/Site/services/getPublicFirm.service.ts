import type { PublicFirm } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { publicApi } from '../composables/publicBrand';

export default async function getPublicFirm(): Promise<ApiResponse<PublicFirm>> {
  return publicApi({
    endpoint: 'public/firm',
    method: 'get',
  });
}
