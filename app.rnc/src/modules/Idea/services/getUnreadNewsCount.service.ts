import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** One integer, asked for on every page load — hence its own endpoint rather than the full list. */
export default async function getUnreadNewsCount(): Promise<ApiResponse<{ unread: number }>> {
  return API({ endpoint: 'news/unread-count', method: 'get' });
}
