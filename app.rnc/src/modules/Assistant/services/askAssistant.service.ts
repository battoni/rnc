import type { AssistantAnswer } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Ask the assistant: by words, or by a chip's note id (D9). No served note → `note: null` and the specialists. */
export default async function askAssistant(question: string, noteId?: number): Promise<ApiResponse<AssistantAnswer>> {
  return API({
    endpoint: 'assistant/ask',
    method: 'post',
    payload: noteId === undefined ? { question } : { note_id: noteId, question },
  });
}
