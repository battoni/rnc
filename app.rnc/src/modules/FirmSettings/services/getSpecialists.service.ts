import type { Specialist } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The firm's bookable specialists with their weeks (rnc-4 R32, W78). */
export default async function getSpecialists(): Promise<ApiResponse<Specialist[]>> {
  return API({ endpoint: 'specialists', method: 'get' });
}
