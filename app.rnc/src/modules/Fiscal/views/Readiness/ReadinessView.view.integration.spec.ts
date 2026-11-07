import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import {
  MClientNoticeDialog,
  MClientTimeline,
  MReadinessDetail,
  MReadinessStatusChip,
  MReadinessTable,
} from '../../components';
import {
  METALURGICA_DETAIL,
  NOTICE_EVENT,
  READINESS_CRITICO_FIXTURE,
  READINESS_FIXTURE,
  REVIEW_EVENT,
} from '../../test/fixtures';
import ReadinessView from './ReadinessView.view.vue';

/*
 * "Prontidão da NF-e" (rnc-2 F8, R18–R20): the hero over every listed client (at risk of rejection
 * 1115, % complete, críticos, prontos, the bar by status), the status and system filters that ask
 * the API again, the table, a client's issue lines with their sample invoices and its timeline, and
 * "Avisar cliente" — whose registered notice lands on that timeline at once (A17).
 */
const requested: URL[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
  // PrimeVue's Select opens an overlay jsdom cannot drive: a native one with the same contract.
  Select: {
    emits: ['update:modelValue'],
    props: ['inputId', 'modelValue', 'options'],
    template:
      '<select :data-testid="inputId" @change="$emit(\'update:modelValue\', $event.target.value || null)"><option value=""></option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
  },
};

function renderReadiness() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 4, last_name: 'Lima', name: 'Patrícia', role: 'Equipe', role_slug: 'equipe', username: 'patricia' },
    ['leads.read', 'fiscal.read']
  );

  return renderWithPlugins(ReadinessView, {
    global: {
      components: {
        Button,
        MClientNoticeDialog,
        MClientTimeline,
        MReadinessDetail,
        MReadinessStatusChip,
        MReadinessTable,
        Skeleton,
      },
      stubs: STUBS,
    },
    pinia,
  });
}

beforeEach(() => {
  requested.length = 0;
  server.use(
    http.get('http://localhost/fiscal/readiness', ({ request }) => {
      const url = new URL(request.url);

      requested.push(url);

      return HttpResponse.json({
        data: url.searchParams.get('status') === 'critico' ? READINESS_CRITICO_FIXTURE : READINESS_FIXTURE,
      });
    }),
    http.get('http://localhost/fiscal/readiness/7', () => HttpResponse.json({ data: METALURGICA_DETAIL })),
    http.get('http://localhost/clients/7/events', () =>
      HttpResponse.json({ data: [{ ...REVIEW_EVENT, client_id: 7 }] })
    ),
    http.post('http://localhost/fiscal/readiness/7/notice', () =>
      HttpResponse.json({ data: NOTICE_EVENT }, { status: 201 })
    )
  );
});

describe('ReadinessView', () => {
  it('shows the invoices at risk, the KPIs, the bar by status and every listed client', async () => {
    renderReadiness();

    const total = await screen.findByTestId('readiness-risk-total');

    expect(total.getAttribute('data-value')).toBe('3');
    expect(screen.getByTestId('readiness-complete-pct').textContent).toContain('81.4%');
    expect(screen.getByTestId('readiness-critical-count').textContent).toContain('1');
    expect(screen.getByTestId('readiness-ready-count').textContent).toContain('1');
    expect(
      screen.getAllByTestId('readiness-bar-segment').map((segment) => segment.getAttribute('data-status'))
    ).toEqual(['critico', 'ok']);
    expect(screen.getAllByTestId('readiness-row').map((row) => row.getAttribute('data-status'))).toEqual([
      'critico',
      'ok',
      'sem_notas',
    ]);
  });

  it('says a client without imported sales has no notes, never a 0 %', async () => {
    renderReadiness();

    const rows = await screen.findAllByTestId('readiness-row');

    expect(within(rows[2]!).getByTestId('readiness-pct').textContent).toContain('—');
  });

  it('asks the API again when a status is chosen; the hero stays over every client', async () => {
    renderReadiness();

    await screen.findAllByTestId('readiness-row');
    await fireEvent.click(screen.getByTestId('readiness-filter-critico'));

    await waitFor(() => expect(screen.getAllByTestId('readiness-row')).toHaveLength(1));

    expect(requested[requested.length - 1]?.searchParams.get('status')).toBe('critico');
    expect(screen.getByTestId('readiness-filter-critico').getAttribute('aria-pressed')).toBe('true');
    expect(screen.getByTestId('readiness-risk-total').getAttribute('data-value')).toBe('3');
  });

  it('filters by the emitting system the API listed', async () => {
    renderReadiness();

    await screen.findAllByTestId('readiness-row');
    await fireEvent.update(screen.getByTestId('readiness-system'), 'ERP Delta 4.2');

    await waitFor(() => expect(requested[requested.length - 1]?.searchParams.get('system')).toBe('ERP Delta 4.2'));
  });

  it('opens a client with its issue lines, their sample invoices and its timeline', async () => {
    renderReadiness();

    const [first] = await screen.findAllByTestId('readiness-open');

    await fireEvent.click(first!);

    const detail = await screen.findByTestId('readiness-detail');
    const issues = await within(detail).findAllByTestId('readiness-issue');

    expect(issues.map((issue) => issue.getAttribute('data-code'))).toEqual([
      'missing_group',
      'missing_codes',
      'without_test_values',
    ]);
    expect(issues[0]!.getAttribute('data-risk')).toBe('true');
    expect(issues[0]!.textContent).toContain('1042');
    expect(await within(detail).findAllByTestId('timeline-line')).toHaveLength(1);
  });

  it("A17: a registered notice lands on the open client's timeline at once", async () => {
    renderReadiness();

    const [first] = await screen.findAllByTestId('readiness-open');

    await fireEvent.click(first!);

    const detail = await screen.findByTestId('readiness-detail');

    await within(detail).findAllByTestId('timeline-line');
    await fireEvent.click(within(detail).getByTestId('readiness-notify'));
    await fireEvent.click(within(screen.getByTestId('dialog')).getByTestId('notice-register'));

    await waitFor(() => expect(within(detail).getAllByTestId('timeline-line')).toHaveLength(2));

    expect(within(detail).getAllByTestId('timeline-line')[0]!.getAttribute('data-type')).toBe('nfe.aviso');
  });

  it('says the list did not load, and offers to try again', async () => {
    server.use(
      http.get('http://localhost/fiscal/readiness', () => HttpResponse.json({ message: 'boom' }, { status: 500 }))
    );

    renderReadiness();

    expect(await screen.findByRole('alert')).toBeTruthy();
  });
});
