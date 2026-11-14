import type { ConsentVersion, ConsentVersionPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A new consent version; stored answers keep the version they were given under (R33). */
export default async function createConsentVersion(
  payload: ConsentVersionPayload
): Promise<ApiResponse<ConsentVersion>> {
  return API({ endpoint: 'firm/consent-versions', forwardError: true, method: 'post', payload });
}
