import type { NewsEntry } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function getNews(): Promise<ApiResponse<NewsEntry[]>> {
  return API({ endpoint: 'news', method: 'get' });
}
