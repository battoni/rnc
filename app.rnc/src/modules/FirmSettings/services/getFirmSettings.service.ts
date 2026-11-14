import type { FirmSettings } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The signed-in person's firm: contact data, hours and credentials line (rnc-4 R30, C16). */
export default async function getFirmSettings(): Promise<ApiResponse<FirmSettings>> {
  return API({ endpoint: 'firm/settings', method: 'get' });
}
