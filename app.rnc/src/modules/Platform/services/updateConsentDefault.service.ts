import type { ConsentDefault, ConsentDefaultPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A new default sentence; existing firms' versions are never touched (R13, C15). */
export default async function updateConsentDefault(
  payload: ConsentDefaultPayload
): Promise<ApiResponse<ConsentDefault>> {
  return API({ endpoint: 'platform/consent-default', forwardError: true, method: 'put', payload });
}
