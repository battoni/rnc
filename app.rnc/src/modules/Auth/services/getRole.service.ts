import type { Profile } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getRole(id: number): Promise<ApiResponse<Profile>> {
  return API({
    endpoint: `auth/roles/${id}`,
    method: 'get',
  });
}
