import type { GetUsersParams, User } from '../interfaces';
import type { ApiResponse, PaginateResult } from '@Providers';
import { API } from '@Providers';

export default async function getUsers(params: GetUsersParams = {}): Promise<ApiResponse<PaginateResult<User>>> {
  return API({
    endpoint: 'auth/users',
    method: 'get',
    query: { ...params },
  });
}
