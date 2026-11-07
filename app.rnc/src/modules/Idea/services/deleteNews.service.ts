import type { NewsEntry } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

export default async function deleteNews(id: number): Promise<ApiResponse<NewsEntry>> {
  return API({ endpoint: `news/${id}`, method: 'delete' });
}
