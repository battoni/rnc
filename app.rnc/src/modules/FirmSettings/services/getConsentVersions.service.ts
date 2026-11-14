import type { ConsentVersion } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The firm's consent versions, newest first; the first is the one the diagnostic shows (R33). */
export default async function getConsentVersions(): Promise<ApiResponse<ConsentVersion[]>> {
  return API({ endpoint: 'firm/consent-versions', method: 'get' });
}
