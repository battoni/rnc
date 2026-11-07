import type { Idea } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Answers with the whole idea, so a card updates granularly from the response. */
export default async function voteIdea(id: number): Promise<ApiResponse<Idea>> {
  return API({ endpoint: `ideas/${id}/vote`, method: 'post' });
}
