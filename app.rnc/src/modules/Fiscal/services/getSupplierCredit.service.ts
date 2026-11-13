import type { SupplierCreditList } from '../interfaces';
import type { ApiResponse } from '@Providers';
import type { CLIENT_SECTOR } from '@RepricingModule';
import { API } from '@Providers';

/** "Crédito de fornecedores" (R21–R22): the KPIs and the clients of one sector, or of all. */
export default async function getSupplierCredit(
  sector: CLIENT_SECTOR | null
): Promise<ApiResponse<SupplierCreditList>> {
  return API({ endpoint: 'fiscal/supplier-credit', method: 'get', query: { sector } });
}
