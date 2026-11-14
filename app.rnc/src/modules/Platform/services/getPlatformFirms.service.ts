import type { PlatformFirm } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The console's list: every firm, the platform firm first, each with its modules and lead count. */
export default async function getPlatformFirms(): Promise<ApiResponse<PlatformFirm[]>> {
  return API({ endpoint: 'platform/firms', method: 'get' });
}
