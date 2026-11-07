import type { KnowledgeNote } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Aposentada, for good (`assistant.manage`, W55). */
export default async function retireNote(id: number): Promise<ApiResponse<KnowledgeNote>> {
  return API({ endpoint: `assistant/notes/${id}/retire`, forwardError: true, method: 'post' });
}
