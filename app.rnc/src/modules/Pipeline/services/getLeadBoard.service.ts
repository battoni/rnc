import type { LeadBoard } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The board: one column per stage with its count and newest cards, narrowed by a search. */
export default async function getLeadBoard(search: string): Promise<ApiResponse<LeadBoard>> {
  return API({ endpoint: 'leads', method: 'get', query: { search: search || undefined } });
}
