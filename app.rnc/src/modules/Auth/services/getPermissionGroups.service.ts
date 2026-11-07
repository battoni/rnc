import type { PermissionGroup } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getPermissionGroupsService(): Promise<ApiResponse<PermissionGroup[]>> {
  return API({
    endpoint: 'auth/permission-groups',
    method: 'get',
  });
}
