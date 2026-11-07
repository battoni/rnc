import type { Brand } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The switch: on, the public site may be shown under it; off, `?marca=` falls back to Franco. */
export default async function toggleBrand(id: number, active: boolean): Promise<ApiResponse<Brand>> {
  return API({ endpoint: `brands/${id}/${active ? 'activate' : 'deactivate'}`, forwardError: true, method: 'post' });
}
