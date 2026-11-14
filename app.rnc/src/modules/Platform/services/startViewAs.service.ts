import type { ViewAsSession } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * "Acessar como suporte" (rnc-4 R26): opens a view-as on the firm; answers the open session, not the
 * user — the caller reads `auth/me` again. 409 `platform.viewAsActive` / 422 `platform.viewAsPlatformFirm`
 * are forwarded for the console to name.
 */
export default async function startViewAs(id: number): Promise<ApiResponse<ViewAsSession>> {
  return API({ endpoint: `platform/firms/${id}/view-as`, forwardError: true, method: 'post' });
}
