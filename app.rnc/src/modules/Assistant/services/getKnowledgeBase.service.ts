import type { KnowledgeBase, KnowledgeNote } from '../interfaces';
import { API } from '@Providers';

/** "Base da Reforma": every note of the firm and how many reviews run out within 7 days (AM19). */
export default async function getKnowledgeBase(): Promise<KnowledgeBase> {
  const response: { data: KnowledgeNote[]; expiring_count: number } = await API({
    endpoint: 'assistant/notes',
    forwardError: true,
    method: 'get',
  });

  return { expiring_count: response.expiring_count, notes: response.data };
}
