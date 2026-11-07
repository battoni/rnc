import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

interface DeleteRoleResponse {
  users: string[];
}

export default async function deleteRole(id: number): Promise<ApiResponse<DeleteRoleResponse>> {
  return API({
    endpoint: `auth/roles/${id}`,
    method: 'delete',
  });
}
