import type { NewsEntry, NewsPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';
import { toNewsFormData } from '../helpers';

/** Always multipart: the entry may carry a cover, and one request shape is easier to trust. */
export default async function createNews(payload: NewsPayload): Promise<ApiResponse<NewsEntry>> {
  return API({
    endpoint: 'news',
    isUpload: true,
    method: 'post',
    payload: toNewsFormData(payload),
  });
}
