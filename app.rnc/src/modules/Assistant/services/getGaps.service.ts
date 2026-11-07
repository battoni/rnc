import type { AssistantGap } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Perguntas sem nota" (R15, `assistant.manage`): the last 30 days' unanswered asks, grouped, most asked first. */
export default async function getGaps(): Promise<ApiResponse<AssistantGap[]>> {
  return API({ endpoint: 'assistant/gaps', method: 'get' });
}
