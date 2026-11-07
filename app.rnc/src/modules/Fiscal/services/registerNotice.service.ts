import type { ClientEvent } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Registrar aviso" (R20): one `nfe.aviso` line on the client's timeline, answered back. Nothing is sent (D12). */
export default async function registerNotice(clientId: number): Promise<ApiResponse<ClientEvent>> {
  return API({ endpoint: `fiscal/readiness/${clientId}/notice`, method: 'post' });
}
