import type { NetworkLedger } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The network's month (`GET ledger?month=YYYY-MM`, rnc-2 R11); without a month, this one in São Paulo. */
export default async function getLedger(month: string | null): Promise<ApiResponse<NetworkLedger>> {
  return API({ endpoint: 'ledger', method: 'get', query: { month: month ?? undefined } });
}
