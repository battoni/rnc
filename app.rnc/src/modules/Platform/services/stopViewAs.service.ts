import type { ViewAsSession } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** "Sair" (rnc-4 R26): closes the view-as as `sair`; null when none was active (W85), never an error. */
export default async function stopViewAs(): Promise<ApiResponse<ViewAsSession | null>> {
  return API({ endpoint: 'platform/view-as', forwardError: true, method: 'delete' });
}
