import type { MarkRepricingPayload, MarkRepricingResult } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Marks the chosen clients for the repricing: each stores `{at, by, pct, suggested}`. */
export default async function markRepricing(payload: MarkRepricingPayload): Promise<ApiResponse<MarkRepricingResult>> {
  return API({ endpoint: 'repricing/mark', method: 'post', payload });
}
