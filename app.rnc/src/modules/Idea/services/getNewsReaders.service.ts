import type { NewsReader } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Who read an entry. The writer's alone — the endpoint is closed by `news.manage`. */
export default async function getNewsReaders(id: number): Promise<ApiResponse<NewsReader[]>> {
  return API({ endpoint: `news/${id}/readers`, method: 'get' });
}
