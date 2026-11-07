import type { RequestEmailOtpPayload, RequestEmailOtpResponse } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Asks for the verification code to be sent again.
 *
 * Always answers `{ sent: true }`, including for an address with nothing pending — the API
 * refuses to say whether an address is registered here, and the screen must not imply it
 * either.
 */
export default async function requestEmailOtpService(
  payload: RequestEmailOtpPayload
): Promise<ApiResponse<RequestEmailOtpResponse>> {
  return API({
    payload,
    endpoint: 'auth/email/code',
    method: 'post',
  });
}
