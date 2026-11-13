import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Cláusula incluída" (rnc-3 R22): the contract has its Reforma clause now, and leaves the alert. */
export default async function markContractClause(contractId: number): Promise<ApiResponse<unknown>> {
  return API({ endpoint: `contracts/${contractId}/clause`, method: 'post' });
}
