import type { KnowledgeNote } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Revisada, by a specialist who did not write the last change (R11): else 403 `not_reviewer`. */
export default async function reviewNote(id: number): Promise<ApiResponse<KnowledgeNote>> {
  return API({ endpoint: `assistant/notes/${id}/review`, forwardError: true, method: 'post' });
}
