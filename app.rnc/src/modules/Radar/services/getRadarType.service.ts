import type { RadarTypeDetail } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** One Radar row opened (R25): its clients with their proof and access keys. */
export default async function getRadarType(rule: string): Promise<ApiResponse<RadarTypeDetail>> {
  return API({ endpoint: `radar/types/${rule}`, method: 'get' });
}
