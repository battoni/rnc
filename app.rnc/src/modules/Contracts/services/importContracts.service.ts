import type { ContractImportResult } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Sends the contracts CSV (rnc-3 R18). Good lines are stored and bad ones come back in a 200; a
 * file refused whole (422 `import_*`) is the form's to explain, so the error is forwarded.
 */
export default async function importContracts(file: File): Promise<ApiResponse<ContractImportResult>> {
  const payload = new FormData();

  payload.append('file', file);

  return API({ endpoint: 'contracts/import', forwardError: true, isUpload: true, method: 'post', payload });
}
