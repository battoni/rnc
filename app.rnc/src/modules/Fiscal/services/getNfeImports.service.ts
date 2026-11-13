import type { NfeImport } from '../interfaces';
import type { ApiResponse, PaginateResult } from '@Providers';
import { API } from '@Providers';

/** A page of the firm's import batches, newest first (R17). */
export default async function getNfeImports(params: {
  page: number;
  per_page: number;
}): Promise<ApiResponse<PaginateResult<NfeImport>>> {
  return API({ endpoint: 'fiscal/imports', method: 'get', query: { page: params.page, per_page: params.per_page } });
}
