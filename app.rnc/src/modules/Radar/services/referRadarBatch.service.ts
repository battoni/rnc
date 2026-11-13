import type { RadarBatchResult } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * "Encaminhar os N maiores" (R27): the rule's next batch to one covering partner. A refusal the row
 * names by `code` (`partner_not_covering`, `already_referred`) is forwarded.
 */
export default async function referRadarBatch(rule: string, partnerId: number): Promise<ApiResponse<RadarBatchResult>> {
  return API({
    endpoint: 'radar/referrals',
    forwardError: true,
    method: 'post',
    payload: { partner_id: partnerId, rule },
  });
}
