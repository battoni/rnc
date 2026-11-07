import type { Profile } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

interface UpdateRolePayload {
  description?: string;
  name?: string;
  permission_group_ids?: string[];
}

export default async function updateRole(id: number, data: UpdateRolePayload): Promise<ApiResponse<Profile>> {
  return API({
    endpoint: `auth/roles/${id}`,
    method: 'patch',
    payload: data,
  });
}
