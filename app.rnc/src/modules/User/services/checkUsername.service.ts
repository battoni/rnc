import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

interface CheckUsernameParams {
  username: string;
  excludeId?: number;
}

interface CheckUsernameResult {
  available: boolean;
}

export default async function checkUsername(params: CheckUsernameParams): Promise<ApiResponse<CheckUsernameResult>> {
  const searchParams = new URLSearchParams({ username: params.username });
  if (params.excludeId != null) searchParams.set('exclude_id', String(params.excludeId));

  return API({
    endpoint: `auth/users/check-username?${searchParams.toString()}`,
    forwardError: true,
    method: 'get',
  });
}
