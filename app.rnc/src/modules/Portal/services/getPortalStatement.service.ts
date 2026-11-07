import type { PortalStatement } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The signed-in partner's month (`GET portal/statement?month=YYYY-MM`); without a month, this one in São Paulo. */
export default async function getPortalStatement(month: string | null): Promise<ApiResponse<PortalStatement>> {
  return API({ endpoint: 'portal/statement', method: 'get', query: { month: month ?? undefined } });
}
