import type { VerifyPhoneCodePayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import type { User } from '@UserModule';
import { API } from '@Providers';

/** Spends the code. On success the API has already opened the session and answers with the user. */
export default async function verifyPhoneCodeService(payload: VerifyPhoneCodePayload): Promise<ApiResponse<User>> {
  return API({
    payload,
    endpoint: 'auth/phone/verify',
    method: 'post',
  });
}
