import type { ResendPasswordResetPayload, ResendPasswordResetResponse } from '../interfaces';
import type { ApiResponse } from '@Providers';

/**
 * Mock: resend reset code. Replace with `API({ ... })` when the backend exists.
 */

export default async function resendPasswordResetCodeService(
  _payload: ResendPasswordResetPayload
): Promise<ApiResponse<ResendPasswordResetResponse>> {
  return new Promise((resolve) =>
    setTimeout(
      () =>
        resolve({
          data: {
            ok: true,
          },
        }),
      300
    )
  );
}
