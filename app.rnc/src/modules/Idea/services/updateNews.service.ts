import type { NewsEntry, NewsPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';
import { toNewsFormData } from '../helpers';

/**
 * POST with `_method=PATCH`, not a real PATCH — PHP only populates `$_FILES` for POST, so a genuine
 * multipart PATCH arrives at Laravel with the cover silently missing. The route stays
 * `Route::patch`, which is what the endpoint actually is.
 */
export default async function updateNews(id: number, payload: NewsPayload): Promise<ApiResponse<NewsEntry>> {
  const FORM_DATA = toNewsFormData(payload);
  FORM_DATA.append('_method', 'PATCH');

  return API({
    endpoint: `news/${id}`,
    isUpload: true,
    method: 'post',
    payload: FORM_DATA,
  });
}
