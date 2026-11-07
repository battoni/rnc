import type { LoginPasswordCredentialsPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import type { User } from '@UserModule';
import { API } from '@Providers';

/**
 * Phone or e-mail + password against the same door as username login (`auth/login`).
 * The identifier is already normalised to a username string by the form (e-mail as-is,
 * phone as digits).
 */
export default async function LoginPasswordCredentialsService(
  payload: LoginPasswordCredentialsPayload
): Promise<ApiResponse<User>> {
  return API({
    payload: { username: payload.username, password: payload.password },
    endpoint: 'auth/login',
    method: 'post',
  });
}
