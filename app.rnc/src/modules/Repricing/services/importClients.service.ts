import type { ClientImportResult } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Sends the portfolio CSV (R25). A refused file is the form's to explain — line by line, by the
 * API's `code` — so the error is forwarded instead of logged as an unexpected one.
 */
export default async function importClients(file: File): Promise<ApiResponse<ClientImportResult>> {
  const payload = new FormData();

  payload.append('file', file);

  return API({ endpoint: 'clients/import', forwardError: true, isUpload: true, method: 'post', payload });
}
