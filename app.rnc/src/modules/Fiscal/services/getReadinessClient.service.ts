import type { ReadinessDetail } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** One client opened (R19): its row, the issue lines and their sample invoices. */
export default async function getReadinessClient(id: number): Promise<ApiResponse<ReadinessDetail>> {
  return API({ endpoint: `fiscal/readiness/${id}`, method: 'get' });
}
