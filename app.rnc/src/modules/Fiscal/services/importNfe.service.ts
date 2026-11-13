import type { NfeImport } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Sends one `.xml` or `.zip` (R15): the API answers the batch with every refusal by file. A refused
 * request (422 size or kind, 429 throttle) is the form's to explain, so the error is forwarded.
 */
export default async function importNfe(file: File): Promise<ApiResponse<NfeImport>> {
  const payload = new FormData();

  payload.append('file', file);

  return API({ endpoint: 'fiscal/imports', forwardError: true, isUpload: true, method: 'post', payload });
}
