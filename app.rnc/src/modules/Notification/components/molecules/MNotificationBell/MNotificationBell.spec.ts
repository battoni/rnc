import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import {
  ASSISTANT_INBOX_FIXTURE,
  INBOX_FIXTURE,
  LEAD_NOTIFICATION_FIXTURE,
  MEETING_NOTIFICATION_FIXTURE,
  referralStatusNotification,
} from '../../../test/fixtures';
import MNotificationBell from './MNotificationBell.vue';

/*
 * The back office's bell (rnc-1 F4, R17): the unread count, polled every 30 s, a dropdown with one
 * line per notification, and mark-as-read — one or all. rnc-2 F3 (R9, A8): a partner's accept,
 * decline and closing ring it too, each with its own title, the contract value on a closing.
 * rnc-3 F5 adds the Reforma assistant's three:
 * a question handed to a specialist, the specialist's answer, a review that runs out in 7 days.
 */
const API = 'http://localhost/notifications';
let inboxCalls = 0;
const reads: string[] = [];
let readAll = 0;
let inbox = INBOX_FIXTURE;

function renderBell() {
  return renderWithPlugins(MNotificationBell, { global: { components: { Button, Popover } } });
}

beforeEach(() => {
  inboxCalls = 0;
  reads.length = 0;
  readAll = 0;
  inbox = INBOX_FIXTURE;
  server.use(
    http.get(API, () => {
      inboxCalls += 1;

      return HttpResponse.json({ data: inbox });
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

  it("writes the Reforma assistant's three bells as sentences, each with its title (rnc-3 R11, R13, R14)", async () => {
    inbox = ASSISTANT_INBOX_FIXTURE;
    renderBell();
    await screen.findByTestId('bell-count');

    await fireEvent.click(screen.getByTestId('bell'));

    const lines = (await screen.findAllByTestId('bell-item')).map((item) => item.textContent?.replace(/\s+/g, ' '));

    expect(lines[0]).toContain('Question for a specialist');
    expect(lines[0]).toContain('Patrícia Gomes handed you: “Qual o melhor ERP?”');
    expect(lines[1]).toContain('Specialist answer');
    expect(lines[1]).toContain('Mariana Toledo answered: “Qual o melhor ERP?”');
    expect(lines[2]).toContain('Review runs out in 7 days');
    expect(lines[2]).toContain('Your review of “Quando começa a CBS?” is served until 26/11/2026.');
  });

  it("names a partner's accept, decline and closing, the closing with its value (A8)", async () => {
    server.use(
      http.get(API, () =>
        HttpResponse.json({
          data: {
            items: ['aceito', 'recusado', 'fechado'].map((status) => referralStatusNotification(status)),
            unread_count: 3,
          },
        })
      )
    );
    renderBell();
    await screen.findByTestId('bell-count');

    await fireEvent.click(screen.getByTestId('bell'));

    const items = (await screen.findAllByTestId('bell-item')).map((item) => item.textContent ?? '');

    expect(items[0]).toContain('Partner accepted');
    expect(items[0]).toContain('NexoERP (exemplo) accepted the referral of Consultoria Prisma Ltda');
    expect(items[1]).toContain('Partner declined');
    expect(items[1]).toContain('NexoERP (exemplo) declined the referral of Consultoria Prisma Ltda');
    expect(items[2]).toContain('Partner closed a contract');
    expect(items[2]).toMatch(/R\$\s12\.000,00/);
  });
});
