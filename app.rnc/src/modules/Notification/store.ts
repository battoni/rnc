import { defineStore } from 'pinia';
import type { BellNotification, NotificationInbox } from './interfaces';
import { GetNotificationsService, MarkAllNotificationsReadService, MarkNotificationReadService } from './services';

interface NotificationState {
  items: BellNotification[];
  unreadCount: number;
}

/**
 * The bell's state (rnc-1 R17), kept across screens so the count does not blink back to zero on
 * every navigation. Each mark-as-read updates the one entry from the API's answer (granular).
 */
const useNotificationStore = defineStore('notification', {
  state: (): NotificationState => ({
    items: [],
    unreadCount: 0,
  }),
  actions: {
    load(): Promise<void> {
      return GetNotificationsService().then(({ data }) => this.setInbox(data));
    },
    setInbox(inbox: NotificationInbox): void {
      this.items = inbox.items;
      this.unreadCount = inbox.unread_count;
    },
    markRead(id: string): Promise<void> {
      return MarkNotificationReadService(id).then(({ data }) => {
        const wasUnread = this.items.some((item) => item.id === id && item.read_at === null);

        this.items = this.items.map((item) => (item.id === id ? data : item));

        if (wasUnread) this.unreadCount = Math.max(0, this.unreadCount - 1);
      });
    },
    markAllRead(): Promise<void> {
      return MarkAllNotificationsReadService().then(({ data }) => {
        const READ_AT = new Date().toISOString();

        this.items = this.items.map((item) => ({ ...item, read_at: item.read_at ?? READ_AT }));
        this.unreadCount = data.unread_count;
      });
    },
  },
});

export { useNotificationStore };
