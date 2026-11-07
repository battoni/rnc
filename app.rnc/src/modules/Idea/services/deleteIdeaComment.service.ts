import type { IdeaComment } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function deleteIdeaComment(commentId: number): Promise<ApiResponse<IdeaComment>> {
  return API({ endpoint: `ideas/comments/${commentId}`, method: 'delete' });
}
