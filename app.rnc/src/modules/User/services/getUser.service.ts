import type { User } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getUser(id: number): Promise<ApiResponse<User>> {
  return API({
    endpoint: `auth/users/${id}`,
    method: 'get',
  });
}
