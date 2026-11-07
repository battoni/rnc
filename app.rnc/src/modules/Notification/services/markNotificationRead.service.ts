import type { BellNotification } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** One notification read; answers it back with its `read_at`. */
export default async function markNotificationRead(id: string): Promise<ApiResponse<BellNotification>> {
  return API({ endpoint: `notifications/${encodeURIComponent(id)}/read`, method: 'patch' });
}
