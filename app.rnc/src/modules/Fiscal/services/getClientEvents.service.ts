import type { ClientEvent } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A client's timeline (`GET clients/{id}/events`, Portfolio), newest first. */
export default async function getClientEvents(clientId: number): Promise<ApiResponse<ClientEvent[]>> {
  return API({ endpoint: `clients/${clientId}/events`, method: 'get' });
}
