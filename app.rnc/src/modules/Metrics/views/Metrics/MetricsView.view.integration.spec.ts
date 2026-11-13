import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { MFunnelChart, MFunnelTable } from '../../components';
import { FUNNEL_30_FIXTURE, FUNNEL_7_FIXTURE } from '../../test/fixtures';
import MetricsView from './MetricsView.view.vue';

/*
 * Reinaldo's funnel (rnc-1 F7, R24): the seven steps with their counts and drop-off, the 7/30-day
 * toggle that asks the API again, and the tables by source and by sector page.
 */
const requested: URL[] = [];
let failNext = false;

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

function renderMetrics() {
  return renderWithPlugins(MetricsView, { global: { components: { Button, MFunnelChart, MFunnelTable, Skeleton } } });
}

beforeEach(() => {
  requested.length = 0;
  failNext = false;
  server.use(
    http.get('http://localhost/metrics/funnel', ({ request }) => {
      const url = new URL(request.url);

      requested.push(url);

      if (failNext) {
        failNext = false;

        return HttpResponse.json({ message: 'boom' }, { status: 500 });
      }

      return HttpResponse.json({ data: url.searchParams.get('days') === '7' ? FUNNEL_7_FIXTURE : FUNNEL_30_FIXTURE });
    })
  );
});

describe('MetricsView', () => {
  it('opens on the last 30 days with the seven steps in order', async () => {
    renderMetrics();

    const steps = await screen.findAllByTestId('funnel-step');

    expect(requested[0]?.searchParams.get('days')).toBe('30');
    expect(steps.map((step) => step.getAttribute('data-step'))).toEqual([
      'visitas',
      'cta',
      'inicio',
      'contato',
      'resultado',
      'agendou',
      'cliente',
    ]);
    expect(steps.map((step) => step.getAttribute('data-value'))).toEqual(['400', '220', '40', '25', '20', '10', '3']);
  });

  it('draws each bar against the top of the funnel and shows the drop-off from the step before', async () => {
    renderMetrics();

    const steps = await screen.findAllByTestId('funnel-step');
    const bar = (index: number) => within(steps[index]!).getByTestId('funnel-bar') as HTMLElement;

    expect(bar(0).style.width).toBe('100%');
    expect(bar(1).style.width).toBe('55%');
    expect(bar(2).style.width).toBe('10%');
    expect(within(steps[0]!).queryByTestId('funnel-drop')).toBeNull();
    expect(within(steps[1]!).getByTestId('funnel-drop').textContent).toContain('45');
    expect(within(steps[1]!).getByTestId('funnel-drop').getAttribute('data-lost')).toBe('180');
  });

  it('asks the API for 7 days when the toggle is pressed, and marks it pressed', async () => {
    renderMetrics();
    await screen.findAllByTestId('funnel-step');

    const week = screen.getByTestId('metrics-days-7');

    await fireEvent.click(week);

    await waitFor(() => expect(screen.getAllByTestId('funnel-step')[0]?.getAttribute('data-value')).toBe('90'));
    expect(requested[requested.length - 1]?.searchParams.get('days')).toBe('7');
    expect(week.getAttribute('aria-pressed')).toBe('true');
    expect(screen.getByTestId('metrics-days-30').getAttribute('aria-pressed')).toBe('false');
  });

  it('lists every source and every sector page with its counts', async () => {
    renderMetrics();

    const sources = await screen.findAllByTestId('source-row');
    const sectors = screen.getAllByTestId('sector-row');

    expect(sources.map((row) => row.getAttribute('data-key'))).toEqual(['organico', 'pago', 'parceiro', 'direto']);
    expect(
      within(sources[1]!)
        .getAllByRole('cell')
        .map((cell) => cell.textContent?.trim())
    ).toContain('68');
    expect(sectors.map((row) => row.getAttribute('data-key'))).toEqual(['comercio', 'agro']);
    expect(
      within(sectors[0]!)
        .getAllByRole('cell')
        .map((cell) => cell.textContent?.trim())
    ).toContain('27');
  });

  it("splits the funnel by channel from the diagnostic's start on (rnc-2 R14, A12)", async () => {
    renderMetrics();

    const channels = await screen.findAllByTestId('channel-row');

    expect(channels.map((row) => row.getAttribute('data-key'))).toEqual(['formulario', 'chat']);
    expect(within(channels[1]!).getByRole('rowheader').textContent?.trim()).toBe('Chat');
    expect(
      within(channels[1]!)
        .getAllByRole('cell')
        .map((cell) => cell.textContent?.trim())
    ).toEqual(['5', '4', '3', '2', '1', '40.0%']);
    expect(screen.getByRole('table', { name: 'By channel' })).toBeTruthy();
  });

  it('offers a retry when the funnel cannot be read', async () => {
    failNext = true;
    renderMetrics();

    const alert = await screen.findByRole('alert');

    await fireEvent.click(within(alert).getByRole('button'));

    expect(await screen.findAllByTestId('funnel-step')).toHaveLength(7);
  });
});
