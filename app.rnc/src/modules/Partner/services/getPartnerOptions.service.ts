import type { PartnerOption } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Every partner as `{value, label, active}`, inactive too — the trail's filter. */
export default async function getPartnerOptions(): Promise<ApiResponse<PartnerOption[]>> {
  return API({ endpoint: 'partners/options', method: 'get' });
}
