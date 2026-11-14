import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import ViewAsLogView from './ViewAsLogView.view.vue';

/*
 * rnc-4 A42/A43 (R26): the console lists every view-as — who entered which firm, from which address,
 * when it started and ended, and why (`sair`, `logout`, `expirou`; open while none) — latest first,
 * a page at a time. A failed load says so and offers to try again.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const SESSIONS = [
  {
    end_reason: null,
    ended_at: null,
    firm: { id: 3, name: 'Alvorada Contabilidade', slug: 'alvorada' },
    id: 8,
    ip_address: '127.0.0.1',
    operator: { id: 1, name: 'Guilherme' },
    started_at: '2026-11-14T13:20:00-03:00',
  },
  {
    end_reason: 'sair',
    ended_at: '2026-11-14T13:10:00-03:00',
    firm: { id: 3, name: 'Alvorada Contabilidade', slug: 'alvorada' },
    id: 7,
    ip_address: '127.0.0.1',
    operator: { id: 1, name: 'Guilherme' },
    started_at: '2026-11-14T13:00:00-03:00',
  },
];

const asked: URLSearchParams[] = [];

function renderLog() {
  return renderWithPlugins(ViewAsLogView, { global: { components: { Button, Skeleton } } });
}

beforeEach(() => {
  asked.length = 0;
  server.use(
    http.get('http://localhost/platform/view-as-log', ({ request }) => {
      asked.push(new URL(request.url).searchParams);

      return HttpResponse.json({
        data: { current_page: 1, data: SESSIONS, last_page: 1, per_page: 25, total: 2 },
      });
    })
  );
});

describe('ViewAsLogView', () => {
  it('lists each view-as with its operator, firm, address, times and reason', async () => {
    renderLog();

    await waitFor(() => expect(screen.getAllByTestId('view-as-log-row')).toHaveLength(2));

    const [open, closed] = screen.getAllByTestId('view-as-log-row');

    expect(open?.textContent).toContain('Alvorada Contabilidade');
    expect(open?.textContent).toContain('Guilherme');
    expect(open?.textContent).toContain('127.0.0.1');
    expect(open?.querySelector('[data-testid="view-as-log-reason"]')?.textContent).toContain('Open');
    expect(closed?.querySelector('[data-testid="view-as-log-reason"]')?.textContent).toContain('Left');
    expect(asked[0]?.get('page')).toBe('1');
  });

  it('says when nobody has entered a firm yet', async () => {
    server.use(
      http.get('http://localhost/platform/view-as-log', () =>
        HttpResponse.json({ data: { current_page: 1, data: [], last_page: 1, per_page: 25, total: 0 } })
      )
    );

    renderLog();

    expect(await screen.findByTestId('view-as-log-empty')).toBeTruthy();
  });

  it('says the log did not load and offers to try again', async () => {
    server.use(
      http.get('http://localhost/platform/view-as-log', () => HttpResponse.json({ error: 'boom' }, { status: 500 }))
    );
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderLog();

    expect((await screen.findByRole('alert')).textContent).toContain('could not be loaded');
  });
});
