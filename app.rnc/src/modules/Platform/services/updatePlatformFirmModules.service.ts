import type { FirmModulesPayload, PlatformFirm } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A firm's WHOLE opt-in list (`FirmModulesService::set`); answers the firm it is now. */
export default async function updatePlatformFirmModules(
  id: number,
  payload: FirmModulesPayload
): Promise<ApiResponse<PlatformFirm>> {
  return API({ endpoint: `platform/firms/${id}/modules`, forwardError: true, method: 'put', payload });
}
