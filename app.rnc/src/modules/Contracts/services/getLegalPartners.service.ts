import type { LegalPartner } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Encaminhar ao jurídico"'s picker (rnc-3 R20): the active legal partners, tax law first; `[]` when none. */
export default async function getLegalPartners(): Promise<ApiResponse<LegalPartner[]>> {
  return API({ endpoint: 'contracts/legal-partners', method: 'get' });
}
