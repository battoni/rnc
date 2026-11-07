import type { RequestPasswordResetPayload, RequestPasswordResetResponse } from '../interfaces';
import type { ApiResponse } from '@Providers';

/**
 * Mock: request password reset. Replace with `API({ ... })` when the backend exists.
 */

export default async function requestPasswordResetService(
  _payload: RequestPasswordResetPayload
): Promise<ApiResponse<RequestPasswordResetResponse>> {
  return new Promise((resolve) =>
    setTimeout(
      () =>
        resolve({
          data: {
            token: 'mock-reset-token',
          },
        }),
      400
    )
  );
}
