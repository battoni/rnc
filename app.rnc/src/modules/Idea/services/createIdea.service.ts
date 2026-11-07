import type { Idea, IdeaPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';
import { toIdeaFormData } from '../helpers';

/** Always multipart: the form may carry a screenshot, and one request shape is easier to trust. */
export default async function createIdea(payload: IdeaPayload): Promise<ApiResponse<Idea>> {
  return API({
    endpoint: 'ideas',
    isUpload: true,
    method: 'post',
    payload: toIdeaFormData(payload),
  });
}
