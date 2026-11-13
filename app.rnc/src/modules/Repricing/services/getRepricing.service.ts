import type { RepricingFilters, RepricingList } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The portfolio quoted, filtered by sector and regime, with its totals and spread (R26). */
export default async function getRepricing(filters: RepricingFilters): Promise<ApiResponse<RepricingList>> {
  return API({ endpoint: 'repricing', method: 'get', query: { regime: filters.regime, sector: filters.sector } });
}
