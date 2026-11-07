import type { AssistantSuggestion } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The chips: the served notes' questions, by id (at most 12). */
export default async function getSuggestions(): Promise<ApiResponse<AssistantSuggestion[]>> {
  return API({ endpoint: 'assistant/suggestions', method: 'get' });
}
