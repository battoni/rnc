import type { AssistantQuestion } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The assigned specialist's answer (R14): respondida, and the asker's bell rings. Anyone else 404. */
export default async function answerQuestion(id: number, answer: string): Promise<ApiResponse<AssistantQuestion>> {
  return API({ endpoint: `assistant/questions/${id}/answer`, forwardError: true, method: 'post', payload: { answer } });
}
