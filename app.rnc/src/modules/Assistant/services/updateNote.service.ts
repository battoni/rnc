import type { KnowledgeNote, NotePayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** An edit: a content change sends a reviewed note back to rascunho (D7, W53). */
export default async function updateNote(id: number, payload: NotePayload): Promise<ApiResponse<KnowledgeNote>> {
  return API({ endpoint: `assistant/notes/${id}`, forwardError: true, method: 'patch', payload });
}
