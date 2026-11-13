import type { ContractAlert } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Alerta de contratos" (rnc-3 R19): the KPIs and the at-risk contracts, most exposed first. */
export default async function getContracts(): Promise<ApiResponse<ContractAlert>> {
  return API({ endpoint: 'contracts', method: 'get' });
}
