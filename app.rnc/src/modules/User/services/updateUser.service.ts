import type { UpdateUserPayload, User } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function updateUser(id: number, payload: UpdateUserPayload): Promise<ApiResponse<User>> {
  return API({
    endpoint: `auth/users/${id}`,
    method: 'patch',
    payload,
  });
}
