import type { PlatformFirm } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * A firm's logo (rnc-4 R27): one file as `logo`; answers the firm with its `logo_url`. A refusal (type,
 * size, sides — `UploadLogoRequest`) is the field's to name, so the error is forwarded.
 */
export default async function uploadFirmLogo(id: number, file: File): Promise<ApiResponse<PlatformFirm>> {
  const payload = new FormData();

  payload.append('logo', file);

  return API({ endpoint: `platform/firms/${id}/logo`, forwardError: true, isUpload: true, method: 'post', payload });
}
