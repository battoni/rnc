import type { Idea, IdeaPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';
import { toIdeaFormData } from '../helpers';

/**
 * POST with `_method=PATCH`, not a real PATCH.
 *
 * PHP only populates `$_FILES` for POST, so a genuine multipart PATCH arrives at Laravel with the
 * file missing and no error to show for it. Method spoofing is the standard way out and the route
 * stays `Route::patch`, which is what the endpoint actually is.
 */
export default async function updateIdea(id: number, payload: IdeaPayload): Promise<ApiResponse<Idea>> {
  const FORM_DATA = toIdeaFormData(payload);
  FORM_DATA.append('_method', 'PATCH');

  return API({
    endpoint: `ideas/${id}`,
    isUpload: true,
    method: 'post',
    payload: FORM_DATA,
  });
}
