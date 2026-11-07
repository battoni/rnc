import type { AssistantQuestion } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The inbox (R14): a specialist's own questions, every one with `assistant.manage`; anyone else 403. */
export default async function getQuestions(): Promise<ApiResponse<AssistantQuestion[]>> {
  return API({ endpoint: 'assistant/questions', forwardError: true, method: 'get' });
}
