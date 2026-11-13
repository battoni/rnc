import type { LeadMove, MoveLeadPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A card's move; answers the card as it is now and the timeline line it wrote. */
export default async function moveLeadStage(id: number, payload: MoveLeadPayload): Promise<ApiResponse<LeadMove>> {
  return API({ endpoint: `leads/${id}/stage`, method: 'patch', payload });
}
