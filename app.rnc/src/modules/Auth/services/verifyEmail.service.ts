import type { VerifyEmailPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import type { User } from '@UserModule';
import { API } from '@Providers';

/** Exchanges the six digits for an account, already signed in. */
export default async function verifyEmailService(payload: VerifyEmailPayload): Promise<ApiResponse<User>> {
  return API({
    payload,
    endpoint: 'auth/email/verify',
    method: 'post',
  });
}
