import type { Idea } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getIdea(id: number): Promise<ApiResponse<Idea>> {
  return API({ endpoint: `ideas/${id}`, method: 'get' });
}
