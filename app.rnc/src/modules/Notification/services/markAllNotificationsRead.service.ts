import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** Every notification of the person read; answers the count left (0). */
export default async function markAllNotificationsRead(): Promise<ApiResponse<{ unread_count: number }>> {
  return API({ endpoint: 'notifications/read-all', method: 'post' });
}
