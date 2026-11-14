import type { PlatformFirm, UpdateFirmLookPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A firm's look; answers the firm it is now. A refusal comes back by field (`brand.lowContrast`). */
export default async function updatePlatformFirm(
  id: number,
  payload: UpdateFirmLookPayload
): Promise<ApiResponse<PlatformFirm>> {
  return API({ endpoint: `platform/firms/${id}`, forwardError: true, method: 'patch', payload });
}
