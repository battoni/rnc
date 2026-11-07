import type { Referral } from '../interfaces';
import type { ApiResponse, PaginateResult } from '@Providers';
import { API } from '@Providers';

/** A page of the trail, newest first; one partner's when `partnerId` is set. */
export default async function getReferrals(params: {
  page: number;
  partnerId: number | null;
  per_page: number;
}): Promise<ApiResponse<PaginateResult<Referral>>> {
  return API({
    endpoint: 'referrals',
    method: 'get',
    query: { page: params.page, partner_id: params.partnerId ?? undefined, per_page: params.per_page },
  });
}
