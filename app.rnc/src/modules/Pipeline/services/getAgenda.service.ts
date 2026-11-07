import type { AgendaWeek } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The working week holding `week` (YYYY-MM-DD), or this week. */
export default async function getAgenda(week: string | null): Promise<ApiResponse<AgendaWeek>> {
  return API({ endpoint: 'agenda', method: 'get', query: { week: week ?? undefined } });
}
