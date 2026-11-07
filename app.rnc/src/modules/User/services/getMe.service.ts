import type { User } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

interface GetMeParams {
  cacheBust?: boolean;
}

export default async function getMe(params?: GetMeParams): Promise<ApiResponse<User>> {
  const endpoint =
    params?.cacheBust === true ? `auth/me?${new URLSearchParams({ _: String(Date.now()) }).toString()}` : 'auth/me';

  return API({
    endpoint,
    method: 'get',
  });
}
