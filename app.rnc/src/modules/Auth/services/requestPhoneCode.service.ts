import type { LoginCodeRequested, RequestPhoneCodePayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Asks for a sign-in code for the phone. The API answers the same whether or not the phone has an
 * account, so nothing after this call may say "we found you".
 */
export default async function requestPhoneCodeService(
  payload: RequestPhoneCodePayload
): Promise<ApiResponse<LoginCodeRequested>> {
  return API({
    payload,
    endpoint: 'auth/phone/code',
    method: 'post',
  });
}
