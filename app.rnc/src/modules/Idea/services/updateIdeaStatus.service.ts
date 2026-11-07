import type { Idea, IdeaStatusPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function updateIdeaStatus(id: number, payload: IdeaStatusPayload): Promise<ApiResponse<Idea>> {
  return API({ endpoint: `ideas/${id}/status`, method: 'patch', payload });
}
