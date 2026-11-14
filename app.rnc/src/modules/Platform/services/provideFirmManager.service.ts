import type { FirmManagerPayload, ManagerLogin } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The firm manager's login, made or reset by username (W54); the password comes back once. */
export default async function provideFirmManager(
  id: number,
  payload: FirmManagerPayload
): Promise<ApiResponse<ManagerLogin>> {
  return API({ endpoint: `platform/firms/${id}/manager`, forwardError: true, method: 'post', payload });
}
