import type { LedgerModelSettings } from '../interfaces';
import type { LEDGER_MODEL } from '@PortalModule';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The one money model of the whole network (`PATCH ledger/model`, D10); answers the model as it is now. */
export default async function updateLedgerModel(model: LEDGER_MODEL): Promise<ApiResponse<LedgerModelSettings>> {
  return API({ endpoint: 'ledger/model', forwardError: true, method: 'patch', payload: { model } });
}
