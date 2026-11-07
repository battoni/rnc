import type { AssistantQuestion } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Hand an unanswered question to an active specialist of the firm (R13): one question, one bell. */
export default async function handOffQuestion(
  question: string,
  specialistId: number
): Promise<ApiResponse<AssistantQuestion>> {
  return API({
    endpoint: 'assistant/questions',
    forwardError: true,
    method: 'post',
    payload: { question, specialist_id: specialistId },
  });
}
