import type { CreateFirmPayload, ProvisionedFirm } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A new firm and its manager (`FirmProvisioner`); the password comes back in this answer only. */
export default async function createPlatformFirm(payload: CreateFirmPayload): Promise<ApiResponse<ProvisionedFirm>> {
  return API({ endpoint: 'platform/firms', forwardError: true, method: 'post', payload });
}
