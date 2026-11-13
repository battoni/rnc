import type { PortalMe } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The signed-in partner and the firm whose portal it is (`GET portal/me`). */
export default async function getPortalMe(): Promise<ApiResponse<PortalMe>> {
  return API({ endpoint: 'portal/me', method: 'get' });
}
