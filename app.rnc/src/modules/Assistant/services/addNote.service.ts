import type { KnowledgeNote, NotePayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** A new note, born rascunho (R10); with `from_question` it is "Virar nota" (R14). */
export default async function addNote(payload: NotePayload): Promise<ApiResponse<KnowledgeNote>> {
  return API({ endpoint: 'assistant/notes', forwardError: true, method: 'post', payload });
}
