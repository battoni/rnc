import type { ApiResponse } from '@Providers';
import type { User } from '@UserModule';
import { API } from '@Providers';

interface LoginRequestPayload {
  username: string;
  password: string;
}

export default async function loginRequest(payload: LoginRequestPayload): Promise<ApiResponse<User>> {
  return API({
    payload,
    endpoint: 'auth/login',
    method: 'post',
  });
}
