import type { VerifyLoginCodePayload, VerifyLoginCodeResponse } from '../interfaces';
import type { ApiResponse } from '@Providers';

export default async function verifyLoginCodeService(
  _payload: VerifyLoginCodePayload
): Promise<ApiResponse<VerifyLoginCodeResponse>> {
  return new Promise((resolve) =>
    setTimeout(
      () =>
        resolve({
          data: {
            email: 'mock.user@rnc.dev',
            id: 1,
            name: 'Mock User',
          },
        }),
      500
    )
  );
}
