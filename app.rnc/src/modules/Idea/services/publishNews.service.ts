import type { NewsEntry } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * Flip one entry between draft and published, and nothing else.
 *
 * A real PATCH with a JSON body, unlike `UpdateNewsService` — there is no file here, so none of the
 * `_method` spoofing that multipart forces on us applies. It is a separate endpoint because the
 * full update reads an absent field as "clear it": publishing through it would need the whole
 * article resent, and would quietly wipe whatever the caller forgot to include.
 */
export default async function publishNews(id: number, publish: boolean): Promise<ApiResponse<NewsEntry>> {
  return API({
    endpoint: `news/${id}/publish`,
    method: 'patch',
    payload: { publish },
  });
}
