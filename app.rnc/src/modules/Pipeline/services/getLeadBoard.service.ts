import type { LeadBoard } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * The board: one column per stage with its count and newest cards, narrowed by a search and by a
 * brand (rnc-3 R4: a sub-brand's slug, or `main`); null asks for every brand.
 */
export default async function getLeadBoard(
  search: string,
  brand: string | null = null
): Promise<ApiResponse<LeadBoard>> {
  return API({ endpoint: 'leads', method: 'get', query: { brand, search: search || undefined } });
}
