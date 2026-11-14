import type { PlatformFirm } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Takes a firm's logo away (rnc-4 R27); answers the firm, `logo_url` null — 200 even without one. */
export default async function removeFirmLogo(id: number): Promise<ApiResponse<PlatformFirm>> {
  return API({ endpoint: `platform/firms/${id}/logo`, forwardError: true, method: 'delete' });
}
