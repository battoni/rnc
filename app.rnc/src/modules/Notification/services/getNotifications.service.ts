import type { NotificationInbox } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { API } from '@Providers';

/** The signed-in person's unread count and latest 20 notifications. */
export default async function getNotifications(): Promise<ApiResponse<NotificationInbox>> {
  return API({ endpoint: 'notifications', method: 'get' });
}
