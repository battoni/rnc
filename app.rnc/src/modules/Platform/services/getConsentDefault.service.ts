import type { ConsentDefault } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The platform's default consent sentence — what the next firm's version 1 copies (R13). */
export default async function getConsentDefault(): Promise<ApiResponse<ConsentDefault>> {
  return API({ endpoint: 'platform/consent-default', method: 'get' });
}
