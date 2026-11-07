import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function logout(): Promise<ApiResponse<null>> {
  return API({
    endpoint: 'auth/logout',
    method: 'post',
  });
}
