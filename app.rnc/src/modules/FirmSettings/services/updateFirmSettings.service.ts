import type { FirmSettings, FirmSettingsPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The firm's contact data and hours; a 422 names the field and the form shows it (celer-07). */
export default async function updateFirmSettings(payload: FirmSettingsPayload): Promise<ApiResponse<FirmSettings>> {
  return API({ endpoint: 'firm/settings', forwardError: true, method: 'patch', payload });
}
