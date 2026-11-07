import type { RadarSummary } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Radar da carteira" (R24): the totals and one row per rule type, with each row's partners. */
export default async function getRadar(): Promise<ApiResponse<RadarSummary>> {
  return API({ endpoint: 'radar', method: 'get' });
}
