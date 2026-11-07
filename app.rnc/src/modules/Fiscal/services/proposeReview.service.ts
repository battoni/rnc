import type { ClientEvent } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * "Propor revisão" (R22): one `fornecedores.revisao` line on the client's timeline. A client with
 * nothing bought from Simples is refused by `code` (`no_simples_purchases`), so the error is forwarded.
 */
export default async function proposeReview(clientId: number): Promise<ApiResponse<ClientEvent>> {
  return API({ endpoint: `fiscal/supplier-credit/${clientId}/review`, forwardError: true, method: 'post' });
}
