import type { RegisterUserPayload, RegisterUserResponse } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Starts a registration.
 *
 * What comes back depends on whether the product verifies e-mail. With verification on,
 * the answer names the pending registration and a code is on its way; with it off, the
 * account already exists and the caller is signed in. The caller tells them apart by
 * whether the response carries an `id`, never by reading configuration it cannot see.
 */
export default async function registerUserService(
  payload: RegisterUserPayload
): Promise<ApiResponse<RegisterUserResponse>> {
  return API({
    payload,
    endpoint: 'auth/register',
    method: 'post',
  });
}
