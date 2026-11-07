import type { Briefing } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Briefing do dia" (rnc-3 R23–R25): the day's KPIs, the three actions and the three panels. */
export default async function getBriefing(): Promise<ApiResponse<Briefing>> {
  return API({ endpoint: 'briefing', method: 'get' });
}
