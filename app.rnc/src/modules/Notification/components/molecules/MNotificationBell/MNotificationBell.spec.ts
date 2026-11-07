import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { INBOX_FIXTURE, LEAD_NOTIFICATION_FIXTURE, MEETING_NOTIFICATION_FIXTURE } from '../../../test/fixtures';
import MNotificationBell from './MNotificationBell.vue';

/*
 * The back office's bell (rnc-1 F4, R17): the unread count, polled every 30 s, a dropdown with one
 * line per notification, and mark-as-read — one or all.
 */
const API = 'http://localhost/notifications';
let inboxCalls = 0;
const reads: string[] = [];
let readAll = 0;

function renderBell() {
  return renderWithPlugins(MNotificationBell, { global: { components: { Button, Popover } } });
}

beforeEach(() => {
  inboxCalls = 0;
  reads.length = 0;
  readAll = 0;
  server.use(
    http.get(API, () => {
      inboxCalls += 1;

      return HttpResponse.json({ data: INBOX_FIXTURE });
    }),
    http.patch(`${API}/:id/read`, ({ params }) => {
      reads.push(String(params.id));

      return HttpResponse.json({ data: { ...MEETING_NOTIFICATION_FIXTURE, read_at: '2026-11-19T10:10:00-03:00' } });
    }),
    http.post(`${API}/read-all`, () => {
      readAll += 1;

      return HttpResponse.json({ data: { unread_count: 0 } });
    })
  );
});

afterEach(() => vi.useRealTimers());

describe('MNotificationBell', () => {
  it('shows the unread count on the bell', async () => {
    renderBell();

    expect((await screen.findByTestId('bell-count')).textContent?.trim()).toBe('2');
    expect(screen.getByTestId('bell').getAttribute('aria-label')).toMatch(/2 unread/);
  });

  it('asks the API again every 30 seconds', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });

    renderBell();
    await waitFor(() => expect(inboxCalls).toBe(1));

    await vi.advanceTimersByTimeAsync(30_000);
    await waitFor(() => expect(inboxCalls).toBe(2));

    await vi.advanceTimersByTimeAsync(29_000);
    expect(inboxCalls).toBe(2);
  });

  it('lists each notification as a sentence and marks one as read', async () => {
    renderBell();
    await screen.findByTestId('bell-count');

    await fireEvent.click(screen.getByTestId('bell'));

    const items = await screen.findAllByTestId('bell-item');

    expect(items).toHaveLength(2);
    expect(items[0]!.textContent).toContain('Clínica Horizonte');
    expect(items[0]!.textContent).toContain('Juliana Reis');
    expect(items[1]!.textContent).toContain(LEAD_NOTIFICATION_FIXTURE.company);

    await fireEvent.click(screen.getAllByTestId('bell-read')[0]!);

    await waitFor(() => expect(reads).toEqual([MEETING_NOTIFICATION_FIXTURE.id]));
    expect(screen.getByTestId('bell-count').textContent?.trim()).toBe('1');
    expect(screen.getAllByTestId('bell-read')).toHaveLength(1);
  });

  it('marks them all as read', async () => {
    renderBell();
    await screen.findByTestId('bell-count');

    await fireEvent.click(screen.getByTestId('bell'));
    await fireEvent.click(await screen.findByTestId('bell-read-all'));

    await waitFor(() => expect(readAll).toBe(1));
    expect(screen.queryByTestId('bell-count')).toBeNull();
  });
});
