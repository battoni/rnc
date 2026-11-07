import type { Idea } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Answers with the CANONICAL idea — the row whose support count just changed. */
export default async function mergeIdea(id: number, targetId: number): Promise<ApiResponse<Idea>> {
  return API({ endpoint: `ideas/${id}/merge`, method: 'post', payload: { targetId } });
}
