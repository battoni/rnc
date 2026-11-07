import type { Profile } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getRoles(): Promise<ApiResponse<Profile[]>> {
  return API({
    endpoint: 'auth/roles',
    method: 'get',
  });
}
