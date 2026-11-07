import type { Profile } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

interface UpdateRolePermissionsPayload {
  permissions: Record<string, boolean>;
}

export default async function updateRolePermissions(
  id: number,
  permissions: Record<string, boolean>
): Promise<ApiResponse<Profile>> {
  return API({
    endpoint: `auth/roles/${id}/permissions`,
    method: 'patch',
    payload: { permissions } as UpdateRolePermissionsPayload,
  });
}
