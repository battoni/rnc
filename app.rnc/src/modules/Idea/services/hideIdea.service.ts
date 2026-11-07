import type { Idea } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A toggle: the same call takes a hidden idea back onto the board. */
export default async function hideIdea(id: number): Promise<ApiResponse<Idea>> {
  return API({ endpoint: `ideas/${id}/hide`, method: 'patch' });
}
