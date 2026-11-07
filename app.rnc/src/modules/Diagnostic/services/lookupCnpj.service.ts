import type { CnpjLookupResult } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The CNPJ path: the API checks the digits (422) and asks BrasilAPI (R9, AM10). */
export default async function lookupCnpj(token: string, cnpj: string): Promise<ApiResponse<CnpjLookupResult>> {
  return API({ endpoint: `public/diagnostics/${encodeURIComponent(token)}/cnpj`, method: 'post', payload: { cnpj } });
}
