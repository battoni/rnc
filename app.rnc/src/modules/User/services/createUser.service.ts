import type { CreateUserPayload, User } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function createUser(payload: CreateUserPayload): Promise<ApiResponse<User>> {
  return API({
    endpoint: 'auth/users',
    method: 'post',
    payload,
  });
}
