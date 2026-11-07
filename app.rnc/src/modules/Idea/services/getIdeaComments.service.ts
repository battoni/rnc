import type { IdeaComment } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getIdeaComments(id: number): Promise<ApiResponse<IdeaComment[]>> {
  return API({ endpoint: `ideas/${id}/comments`, method: 'get' });
}
