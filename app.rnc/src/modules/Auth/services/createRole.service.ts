import type { Profile } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

interface CreateRolePayload {
  description?: string;
  name: string;
  permission_group_ids: string[];
}

export default async function createRole(data: CreateRolePayload): Promise<ApiResponse<Profile>> {
  return API({
    endpoint: 'auth/roles',
    method: 'post',
    payload: data,
  });
}
