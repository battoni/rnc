import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/**
 * "Encaminhar ao jurídico" (rnc-3 R20): one contract to one legal partner. A refusal the row names by
 * `code` (`consent_required`, `partner_not_legal`, `already_referred`) is forwarded.
 */
export default async function referContract(contractId: number, partnerId: number): Promise<ApiResponse<unknown>> {
  return API({
    endpoint: `contracts/${contractId}/referrals`,
    forwardError: true,
    method: 'post',
    payload: { partner_id: partnerId },
  });
}
